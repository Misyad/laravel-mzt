<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects a payment (Phase 2B).
 *
 * Idempotent (PRD §21.11): re-verifying with the same target status returns the
 * existing state without creating duplicate logs. Only the transition
 * "WAITING_VERIFICATION → PAID | REJECTED" is allowed (state machine
 * §17.14.3, without `completed` per ADR-017). Every change writes a
 * PaymentLog (§16.7) and dispatches PaymentStatusChanged (ADR-016).
 */
class PaymentVerificationService
{
    public function __construct(
        protected PaymentService $payment,
        protected TicketService $tickets,
    ) {
    }

    /**
     * Verify a payment into the given final status (paid|rejected).
     *
     * @param  string  $status  PaymentStatus::PAID or ::REJECTED
     * @return array{ok: bool, message?: string, code: int, payment?: \App\Models\Payment, changed?: bool}
     */
    public function verify(Payment $payment, User $actor, string $status, ?string $note = null): array
    {
        if (!in_array($status, [PaymentStatus::PAID->value, PaymentStatus::REJECTED->value], true)) {
            return ['ok' => false, 'message' => 'Status verifikasi tidak valid', 'code' => 422];
        }

        // Idempotent: same result already applied -> no-op success.
        if ($payment->status === $status) {
            return ['ok' => true, 'payment' => $payment, 'changed' => false, 'message' => 'Pembayaran sudah dalam status tersebut', 'code' => 200];
        }

        // Only waiting_verification may be verified (state machine §17.14.3).
        if ($payment->status !== PaymentStatus::WAITING_VERIFICATION->value) {
            return ['ok' => false, 'message' => 'Payment tidak dalam status menunggu verifikasi', 'code' => 409];
        }

        $old = $payment->status;

        return DB::transaction(function () use ($payment, $actor, $status, $note, $old) {
            $payment->forceFill([
                'status' => $status,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'updated_by' => $actor->id,
            ]);

            if ($status === PaymentStatus::PAID->value) {
                $payment->paid_at = $payment->paid_at ?? now();
            }

            $payment->save();

            // Audit trail (PRD §16.7) — every status change produces a log.
            $this->log($payment, $old, $status, $actor->id, $note);

            // Keep the order's payment status in sync with outstanding (§9.8).
            $this->payment->syncOrderPaymentStatus($payment->order);

            event(new PaymentStatusChanged($payment, $old, $status, $actor, $note));

            // Paid → the order is now entitled to its ticket (PRD §10.3).
            // Idempotent inside TicketService: an existing ticket is returned.
            $ticket = null;
            if ($status === PaymentStatus::PAID->value && $this->tickets->canIssue($payment->order->fresh())) {
                $issued = $this->tickets->generate($actor, $payment->order->fresh());
                $ticket = $issued['ticket'] ?? null;
            }

            return ['ok' => true, 'payment' => $payment->fresh(), 'ticket' => $ticket, 'changed' => true, 'message' => $status === PaymentStatus::PAID->value ? 'Pembayaran disetujui' : 'Pembayaran ditolak', 'code' => 200];
        });
    }

    /**
     * Write a PaymentLog row for a transition.
     */
    protected function log(Payment $payment, string $old, string $new, int $changedBy, ?string $note): void
    {
        $payment->logs()->create([
            'id_payment' => $payment->id,
            'old_status' => $old,
            'new_status' => $new,
            'note' => $note,
            'changed_by' => $changedBy,
        ]);
    }
}