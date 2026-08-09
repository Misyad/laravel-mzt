<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\Prisensi_kehadiran;
use App\Models\Tanggal_event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * QR Check-in — Phase 2C (PRD §12.3 / §12.4 / §17.8 / §17.14.4 / ADR-011/012).
 *
 * Ticket-based attendance: Ticket → attendance → audit. Only the
 * `issued → checked_in` transition is implemented here; `checked_in → finished`
 * belongs to a later phase and is deliberately NOT handled.
 *
 * Validation chain (READ ONLY first, then a single transaction):
 *  ticket_uuid → ticket exists → event (ticket → order → id_event)
 *  → id_tanggal belongs to that event → status must be ISSUED
 *  → duplicate scan guard → DB transaction → ticket CHECKED_IN + used_at
 *  → prisensi_kehadiran → ticket_log → DB::afterCommit
 *  → TicketStatusChanged(action='check_in').
 *
 * Legacy POST /attendance (attendanceStore) is untouched and keeps working;
 * the new columns are all nullable so both paths coexist.
 */
class CheckInService
{
    /**
     * Check a ticket in on behalf of an operator.
     *
     * @return array{ok: bool, message?: string, code: int, data?: array<string,mixed>}
     */
    public function checkIn(User $actor, string $ticketUuid, int $idTanggal, ?string $gate): array
    {
        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        if (!$ticket) {
            return ['ok' => false, 'message' => 'Tiket tidak ditemukan', 'code' => 404];
        }

        if (!Gate::forUser($actor)->allows('checkIn', $ticket)) {
            return ['ok' => false, 'message' => 'Forbidden', 'code' => 403];
        }

        $order = $ticket->order;
        if (!$order) {
            return ['ok' => false, 'message' => 'Data pesanan tidak ditemukan', 'code' => 422];
        }

        // The day must belong to the ticket's event (ticket → order → id_event).
        $day = Tanggal_event::where('id', $idTanggal)
            ->where('id_event', $order->id_event)
            ->first();
        if (!$day) {
            return ['ok' => false, 'message' => 'Tanggal kegiatan tidak valid untuk tiket ini', 'code' => 422];
        }

        // Only issued tickets may be checked in (ADR-011). Draft / cancelled /
        // revoked / finished are rejected; an already-checked-in ticket is a
        // duplicate scan (PRD §12.4) answered with the first scan info.
        $verdict = $this->verdict($ticket);
        if ($verdict === 'duplicate') {
            return $this->duplicate($ticket);
        }
        if ($verdict === 'rejected') {
            return ['ok' => false, 'message' => 'Tiket tidak dapat digunakan pada status saat ini', 'code' => 409];
        }

        $result = DB::transaction(function () use ($actor, $ticket, $order, $idTanggal, $gate) {
            // Concurrency gate: atomically flip issued → checked_in so a second,
            // simultaneous scan of the same ticket updates zero rows and is
            // answered as a duplicate instead of double-checking-in the ticket.
            $flipped = DB::table('tickets')
                ->where('id', $ticket->id)
                ->where('status', TicketStatus::ISSUED->value)
                ->update([
                    'status' => TicketStatus::CHECKED_IN->value,
                    'used_at' => now(),
                    'updated_by' => $actor->id,
                    'updated_at' => now(),
                ]);

            if ($flipped === 0) {
                return ['conflict' => true];
            }

            $scannedAt = now();

            $attendance = Prisensi_kehadiran::create([
                'id_event' => $order->id_event,
                'id_tanggal' => $idTanggal,
                'id_anggota' => $order->id_anggota,
                'tanggal_kehadiran' => $scannedAt,
                'jam_kehadiran' => $scannedAt->format('H:i:s'),
                'id_ticket' => $ticket->id,
                'gate' => $gate,
                'scanned_at' => $scannedAt,
                'scanned_by' => $actor->id,
            ]);

            $ticket->logs()->create([
                'id_ticket' => $ticket->id,
                'old_status' => TicketStatus::ISSUED->value,
                'new_status' => TicketStatus::CHECKED_IN->value,
                'note' => 'check_in',
                'changed_by' => $actor->id,
            ]);

            // Transactional consistency (ADR-016): the domain signal is only
            // emitted after the surrounding transaction really commits.
            DB::afterCommit(fn () => event(new TicketStatusChanged(
                $ticket,
                TicketStatus::ISSUED->value,
                TicketStatus::CHECKED_IN->value,
                'check_in',
                $actor,
                $gate,
            )));

            return ['conflict' => false, 'attendance' => $attendance];
        });

        if ($result['conflict']) {
            // Lost the race against a parallel scanner — re-read the freshest
            // state so the answer is precise (duplicate vs generic rejection).
            $fresh = Ticket::find($ticket->id);
            if ($fresh && $fresh->status === TicketStatus::CHECKED_IN->value) {
                return $this->duplicate($fresh);
            }

            return ['ok' => false, 'message' => 'Tiket tidak dapat digunakan pada status saat ini', 'code' => 409];
        }

        $ticket->refresh();

        $participant = User::where('id_anggota', $order->id_anggota)->first();

        return [
            'ok' => true,
            'message' => 'Check-in berhasil',
            'code' => 200,
            'data' => [
                'ticket' => $ticket,
                'attendance' => $result['attendance'],
                'participant' => $participant ? [
                    'id' => $participant->id,
                    'id_anggota' => $participant->id_anggota,
                    'name' => $participant->name,
                ] : null,
                'event' => [
                    'id_event' => $order->id_event,
                    'event_name' => $order->event_name,
                ],
            ],
        ];
    }

    /**
     * Pure check-in readiness rule (ADR-011). Readable without a database so it
     * is unit-testable exactly like TicketLifecycleService::canRevoke.
     *
     * @return string  'ok' | 'duplicate' | 'rejected'
     */
    public function verdict(Ticket $ticket): string
    {
        return match ($ticket->status) {
            TicketStatus::ISSUED->value => 'ok',
            TicketStatus::CHECKED_IN->value => 'duplicate',
            default => 'rejected',
        };
    }

    /**
     * Duplicate scan payload (PRD §12.4): first scan info only — the endpoint
     * mutates nothing and emits no domain event.
     *
     * @return array{ok: false, message: string, code: int, data: array<string,mixed>}
     */
    protected function duplicate(Ticket $ticket): array
    {
        $first = Prisensi_kehadiran::where('id_ticket', $ticket->id)
            ->orderBy('id')
            ->first();

        return [
            'ok' => false,
            'message' => 'Tiket sudah digunakan',
            'code' => 409,
            'data' => [
                'first_scanned_at' => $first?->scanned_at,
                'first_scanned_by' => $first?->scanned_by,
            ],
        ];
    }
}
