<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ticket lifecycle operations (PRD §17.14.4 / §21.7).
 *
 *  - reissue: produces a fresh ticket document / on-demand QR without changing
 *    business identity. uuid, nomor_ticket, issued_at, id_order and status all
 *    stay unchanged (rule #3); only a new TicketLog is appended.
 *  - revoke: blocks a ticket from further use; permitted only while not yet
 *    finished, and irreversible by design (state machine §17.14.4).
 *
 * Check-in (issued → checked_in → finished) belongs to Phase 2C and is NOT here.
 */
class TicketLifecycleService
{
    /**
     * Re-issue a usable ticket. Never creates a new ticket row.
     *
     * @return array{ok: bool, message?: string, code: int, ticket?: \App\Models\Ticket}
     */
    public function reissue(User $actor, Ticket $ticket, ?string $note = null): array
    {
        if (!in_array($ticket->status, [TicketStatus::ISSUED->value, TicketStatus::CHECKED_IN->value], true)) {
            return ['ok' => false, 'message' => 'Tiket tidak dapat diterbitkan ulang pada status saat ini', 'code' => 409];
        }

        return DB::transaction(function () use ($actor, $ticket, $note) {
            // Identity preserved: uuid / nomor_ticket / id_order / issued_at / status untouched.
            $ticket->forceFill(['updated_by' => $actor->id])->save();

            $ticket->logs()->create([
                'id_ticket' => $ticket->id,
                'old_status' => $ticket->status,
                'new_status' => $ticket->status,
                'note' => $note ?: 'reissue',
                'changed_by' => $actor->id,
            ]);

            // Generic lifecycle signal (action = reissue), status did not change.
            DB::afterCommit(fn () => event(new TicketStatusChanged($ticket, $ticket->status, $ticket->status, 'reissue', $actor, $note)));

            return ['ok' => true, 'ticket' => $ticket->fresh(), 'message' => 'Tiket berhasil diterbitkan ulang', 'code' => 200];
        });
    }

    /**
     * Revoke a ticket, blocking any future use.
     *
     * @return array{ok: bool, message?: string, code: int, ticket?: \App\Models\Ticket, changed?: bool}
     */
    public function revoke(User $actor, Ticket $ticket, ?string $note = null): array
    {
        if (!$this->canRevoke($ticket)) {
            return ['ok' => false, 'message' => 'Tiket tidak dapat dibatalkan pada status saat ini', 'code' => 409];
        }

        $old = $ticket->status;
        $new = TicketStatus::REVOKED->value;

        return DB::transaction(function () use ($actor, $ticket, $old, $new, $note) {
            $ticket->forceFill([
                'status' => $new,
                'revoked_at' => now(),
                'updated_by' => $actor->id,
            ])->save();

            $ticket->logs()->create([
                'id_ticket' => $ticket->id,
                'old_status' => $old,
                'new_status' => $new,
                'note' => $note,
                'changed_by' => $actor->id,
            ]);

            DB::afterCommit(fn () => event(new TicketStatusChanged($ticket, $old, $new, 'revoke', $actor, $note)));

            return ['ok' => true, 'ticket' => $ticket->fresh(), 'changed' => true, 'message' => 'Tiket dibatalkan', 'code' => 200];
        });
    }

    /**
     * A ticket may only be revoked while it has not yet been fully used
     * (PRD §17.14.4). Cancelled / revoked / finished are terminal.
     */
    public function canRevoke(Ticket $ticket): bool
    {
        return in_array($ticket->status, [
            TicketStatus::DRAFT->value,
            TicketStatus::ISSUED->value,
            TicketStatus::CHECKED_IN->value,
        ], true);
    }
}