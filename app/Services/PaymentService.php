<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * Payment creation and outstanding calculation (Phase 2B).
 *
 * Payment is a child entity of Order (ADR-001). Responsibilities (plan §5.7):
 *  - create a payment on behalf of panitia/operator (cash/sponsor/complimentary
 *    become PAID immediately; transfer becomes WAITING_VERIFICATION)
 *  - upload a payment proof (transition PENDING → WAITING_VERIFICATION)
 *  - keep the order's outstanding / payment_status up to date (§9.8)
 *  - write PaymentLog on every transition (§16.7)
 */
class PaymentService
{
    public function __construct(
        protected PaymentProofService $proofs,
        protected OrderNumberService $orderNumber,
        protected TicketService $tickets,
    ) {
    }

    /**
     * Create a payment for an order.
     *
     * @param  array<array-key,mixed>  $data  expected: method (string), amount (numeric),
     *                                        reference_number?, paid_at (datetime)?, note?
     * @return array{ok: bool, message?: string, code: int, payment?: \App\Models\Payment, ticket?: \App\Models\Ticket}
     */
    public function create(User $actor, Order $order, array $data): array
    {
        $method = strtolower((string) ($data['method'] ?? ''));
        $amount = (float) ($data['amount'] ?? 0);

        if (!in_array($method, PaymentMethod::values(), true)) {
            return ['ok' => false, 'message' => 'Metode pembayaran tidak valid', 'code' => 422];
        }

        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Nominal pembayaran harus lebih dari 0', 'code' => 422];
        }

        $outstanding = $this->outstanding($order);

        // PRD §9.6: payment must not exceed the remaining bill.
        if ($amount - config('payment.amount_epsilon', 0.001) > $outstanding['outstanding']) {
            return ['ok' => false, 'message' => 'Pembayaran melebihi sisa tagihan', 'code' => 422];
        }

        return DB::transaction(function () use ($actor, $order, $data, $method, $amount) {
            $isImmediate = in_array($method, [PaymentMethod::CASH->value, PaymentMethod::SPONSOR->value, PaymentMethod::COMPLIMENTARY->value], true);

            $status = $isImmediate ? PaymentStatus::PAID->value : PaymentStatus::PENDING->value;

            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'nomor_payment' => $this->orderNumber->nextPayment(),
                'id_order' => $order->id,
                'method' => $method,
                'amount' => $amount,
                'status' => $status,
                'paid_at' => ($isImmediate ? now() : ($data['paid_at'] ?? null)),
                'verified_at' => $isImmediate ? now() : null,
                'verified_by' => $isImmediate ? $actor->id : null,
                'reference_number' => $data['reference_number'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->log($payment, PaymentStatus::PENDING->value, $status, $actor->id, $data['note'] ?? null);

            if ($isImmediate) {
                DB::afterCommit(fn () => event(new PaymentStatusChanged($payment, PaymentStatus::PENDING->value, PaymentStatus::PAID->value, $actor)));
            }

            $freshOrder = $order->fresh();
            $this->syncOrderPaymentStatus($freshOrder);

            // Gate 1 (Instant Payment → Ticket): every path that lands on a
            // fully PAID order must produce a ticket, through the same business
            // rule (TicketService::generate). This covers cash / sponsor /
            // complimentary right here, and transfer via PaymentVerificationService
            // on verify. Idempotent: never creates a duplicate ticket.
            $ticket = null;
            if ($this->tickets->canIssue($freshOrder)) {
                $issued = $this->tickets->generate($actor, $freshOrder);
                $ticket = $issued['ticket'] ?? null;
            }

            return ['ok' => true, 'payment' => $payment->fresh(), 'ticket' => $ticket, 'message' => 'Pembayaran berhasil dibuat', 'code' => 201];
        });
    }

    /**
     * Attach a payment proof and move the payment to WAITING_VERIFICATION.
     * A payment is created on the fly (PENDING) if none is open.
     *
     * @param  mixed  $file  UploadedFile
     * @return array{ok: bool, message?: string, code: int, payment?: \App\Models\Payment}
     */
    public function uploadProof(User $user, Order $order, $file, ?string $bank = null, ?string $accountName = null, ?string $notes = null): array
    {
        return DB::transaction(function () use ($user, $order, $file, $bank, $accountName, $notes) {
            $payment = $order->payments()
                ->whereIn('status', [PaymentStatus::PENDING->value, PaymentStatus::REJECTED->value])
                ->latest('id')
                ->first();

            if (!$payment) {
                $amount = max($this->outstanding($order)['outstanding'], 0);
                $amount = $amount > 0 ? $amount : (float) $order->total_amount;

                $payment = Payment::create([
                    'uuid' => (string) Str::uuid(),
                    'nomor_payment' => $this->orderNumber->nextPayment(),
                    'id_order' => $order->id,
                    'method' => PaymentMethod::TRANSFER->value,
                    'amount' => $amount,
                    'status' => PaymentStatus::PENDING->value,
                    'reference_number' => trim(($bank ?? '') . ' ' . ($accountName ?? '')) ?: null,
                    'note' => $notes,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            // Immutable proof record (PRD §16.6): a new row per upload.
            $this->proofs->store($payment, $file, $user);

            $old = $payment->status;
            $next = PaymentStatus::WAITING_VERIFICATION->value;

            $payment->forceFill(['status' => $next, 'updated_by' => $user->id])->save();

            $this->log($payment, $old, $next, $user->id, $notes);
            DB::afterCommit(fn () => event(new PaymentStatusChanged($payment, $old, $next, $user)));

            return ['ok' => true, 'payment' => $payment->fresh(['proofs']), 'message' => 'Bukti pembayaran diunggah', 'code' => 200];
        });
    }

    /**
     * Realtime outstanding for an order (PRD §9.8).
     *
     * @return array{
     *   total: float,
     *   paid: float,
     *   outstanding: float,
     *   payment_status: string
     * }
     */
    public function outstanding(Order $order): array
    {
        $total = (float) $order->total_amount;

        $paid = (float) $order->payments()
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');

        $outstanding = round($total - $paid, 2);

        $paymentStatus = $outstanding <= 0.001
            ? PaymentStatus::PAID->value
            : PaymentStatus::PENDING->value;

        return [
            'total' => round($total, 2),
            'paid' => round($paid, 2),
            'outstanding' => max($outstanding, 0),
            'payment_status' => $paymentStatus,
        ];
    }

    /**
     * Keep `orders.payment_status` consistent with the outstanding calc.
     */
    public function syncOrderPaymentStatus(Order $order): void
    {
        if (!$order) {
            return;
        }

        $sum = $this->outstanding($order);
        $order->forceFill([
            'payment_status' => $sum['payment_status'],
            'updated_by' => $order->updated_by,
        ])->save();
    }

    /**
     * Get the open payment of an order (PENDING / WAITING_VERIFICATION / REJECTED).
     */
    public function activePayment(Order $order): ?Payment
    {
        return $order->payments()
            ->whereIn('status', [
                PaymentStatus::PENDING->value,
                PaymentStatus::WAITING_VERIFICATION->value,
                PaymentStatus::REJECTED->value,
            ])
            ->latest('id')
            ->first();
    }

    /**
     * Write a PaymentLog row.
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