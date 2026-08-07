<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Events\TicketIssued;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues tickets for an Order (PRD §10.1 / §10.3 / ADR-016).
 *
 * A ticket is child of the Order (never of the Event). It may be issued when:
 *   - the event is free  → right after registration (total_amount == 0), or
 *   - the event is paid  → once the order is fully paid (payment_status PAID).
 *
 * Idempotent (PRD §24.11): generating for an order that already owns an issued
 * ticket returns the existing ticket instead of creating a duplicate. The
 * ticket UUID is the public identity and the exact value stored in qr_payload
 * (PRD §10.6) — no QR image file is ever persisted (rendered on demand).
 */
class TicketService
{
    public function __construct(
        protected TicketNumberService $ticketNumber,
    ) {
    }

    /**
     * Issue a ticket for an order when the order satisfies the issuance rules.
     *
     * @return array{ok: bool, message?: string, code: int, ticket?: \App\Models\Ticket, issued?: bool}
     */
    public function generate(User $actor, Order $order, ?string $note = null): array
    {
        // Idempotent (PRD §24.11): an already usable ticket is returned as-is.
        $existing = $order->tickets()
            ->whereIn('status', [TicketStatus::ISSUED->value, TicketStatus::CHECKED_IN->value])
            ->latest('id')
            ->first();

        if ($existing) {
            return ['ok' => true, 'ticket' => $existing, 'issued' => false, 'message' => 'Tiket sudah tersedia', 'code' => 200];
        }

        // Issuance gate (PRD §10.3).
        if (!$this->canIssue($order)) {
            return ['ok' => false, 'message' => 'Order belum memenuhi syarat penerbitan tiket', 'code' => 409];
        }

        return DB::transaction(function () use ($actor, $order, $note) {
            $ticket = $this->allocateTicket($order, $actor);

            $ticket->logs()->create([
                'id_ticket' => $ticket->id,
                'old_status' => TicketStatus::DRAFT->value,
                'new_status' => TicketStatus::ISSUED->value,
                'note' => $note,
                'changed_by' => $actor->id,
            ]);

            // Transactional consistency (Gate 2, ADR-016): only dispatch the
            // domain event after the surrounding transaction really commits.
            // If the transaction rolls back, the callback is dropped and no
            // TicketIssued is ever emitted.
            DB::afterCommit(fn () => event(new TicketIssued($ticket, $actor)));

            return ['ok' => true, 'ticket' => $ticket->fresh(), 'issued' => true, 'message' => 'Tiket berhasil diterbitkan', 'code' => 201];
        });
    }

    /**
     * Revenue gates whether an order is eligible to have an active ticket.
     *
     * - Free event  (total_amount == 0) → registered order is enough.
     * - Paid event  → the order must be PAID.
     */
    public function canIssue(Order $order): bool
    {
        $free = ((float) $order->total_amount) <= 0.001;

        if ($free) {
            return in_array($order->status_registrasi, [OrderStatus::REGISTERED->value, OrderStatus::CONFIRMED->value], true);
        }

        return $order->payment_status === 'paid';
    }

    /**
     * Reserve a unique ticket number, retrying inside the transaction whenever a
     * parallel writer races on the same candidate number (rule 1).
     *
     * @throws \Throwable rethrows after exhausting retries
     */
    protected function allocateTicket(Order $order, User $actor): Ticket
    {
        $maxAttempts = 5;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $number = $this->ticketNumber->make();

            try {
                $uuid = (string) Str::uuid();

                return Ticket::create([
                    'uuid' => $uuid,
                    'nomor_ticket' => $number,
                    'id_order' => $order->id,
                    'qr_payload' => $uuid,
                    'status' => TicketStatus::ISSUED->value,
                    'issued_at' => now(),
                    'expired_at' => null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            } catch (QueryException $e) {
                if (!$this->ticketNumber->isDuplicateViolation($e) || $attempt === $maxAttempts - 1) {
                    throw $e;
                }
            }
        }

        // Unreachable in practice — keeps the linter quiet.
        throw new \RuntimeException('Gagal mengalokasikan nomor tiket');
    }
}