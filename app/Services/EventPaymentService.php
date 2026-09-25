<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DataUser;
use App\Models\EventPaymentEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventPaymentService
{
    public function __construct(
        protected PaymenkuService $paymenku,
        protected OrderNumberService $orderNumber,
        protected PaymentService $payments,
        protected TicketService $tickets,
    ) {}

    public function checkout(User $user, Order $order): array
    {
        if ((string) $user->id_anggota !== (string) $order->id_anggota) {
            return ['ok' => false, 'code' => 403, 'message' => 'Forbidden'];
        }

        if ($order->payment_choice !== 'pay_now') {
            return ['ok' => false, 'code' => 422, 'message' => 'Order tidak menggunakan pembayaran online'];
        }

        if ($order->payment_status === PaymentStatus::PAID->value) {
            return ['ok' => false, 'code' => 409, 'message' => 'Order sudah lunas'];
        }

        $orderId = $order->id;

        return DB::transaction(function () use ($user, $orderId) {
            $lockedOrder = Order::whereKey($orderId)->lockForUpdate()->first();
            if (! $lockedOrder) {
                return ['ok' => false, 'code' => 404, 'message' => 'Order tidak ditemukan'];
            }

            if ((string) $user->id_anggota !== (string) $lockedOrder->id_anggota) {
                return ['ok' => false, 'code' => 403, 'message' => 'Forbidden'];
            }

            if ($lockedOrder->payment_choice !== 'pay_now') {
                return ['ok' => false, 'code' => 422, 'message' => 'Order tidak menggunakan pembayaran online'];
            }

            if ($lockedOrder->payment_status === PaymentStatus::PAID->value) {
                return ['ok' => false, 'code' => 409, 'message' => 'Order sudah lunas'];
            }

            $existing = Payment::where('id_order', $lockedOrder->id)
                ->where('provider', 'paymenku')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return [
                    'ok' => true,
                    'code' => 200,
                    'message' => 'Checkout sudah tersedia',
                    'payment' => $existing,
                    'created' => false,
                ];
            }

            $outstanding = $this->payments->outstanding($lockedOrder);
            $baseAmount = $this->amountInCents($outstanding['outstanding']);
            if ($baseAmount === null || $baseAmount <= 0) {
                return ['ok' => false, 'code' => 409, 'message' => 'Order tidak memiliki tagihan aktif'];
            }
            if ($baseAmount % 100 !== 0) {
                return ['ok' => false, 'code' => 422, 'message' => 'Nominal order tidak didukung payment gateway'];
            }

            $reference = 'EVENT-'.$lockedOrder->uuid;
            $payload = [
                'channel_code' => PaymentMethod::QRIS->value,
                'amount' => intdiv($baseAmount, 100),
                'reference_id' => $reference,
                'customer_name' => (string) $user->name,
                'customer_email' => (string) ($user->email ?: 'anggota@maziltutholiban.org'),
                'return_url' => rtrim((string) config('member_onboarding.frontend_url'), '/').'/portal/orders/'.$lockedOrder->uuid,
            ];
            $dataUser = DataUser::where('id_users', $user->id)->first(['no_hp']);
            if ($dataUser && trim((string) $dataUser->no_hp) !== '') {
                $payload['customer_phone'] = (string) $dataUser->no_hp;
            }

            $response = $this->paymenku->createTransaction($payload, $reference);
            if (! $response['ok']) {
                return [
                    'ok' => false,
                    'code' => $response['code'],
                    'message' => $response['message'],
                ];
            }

            $gateway = $response['data'];
            $transactionId = trim((string) ($gateway['trx_id'] ?? ''));
            $gatewayTotal = $this->amountInCents($gateway['amount'] ?? null);
            if ($transactionId === '') {
                return ['ok' => false, 'code' => 502, 'message' => 'Gateway tidak mengembalikan trx_id'];
            }
            if (isset($gateway['reference_id']) && ! hash_equals($reference, (string) $gateway['reference_id'])) {
                return ['ok' => false, 'code' => 502, 'message' => 'Reference gateway tidak valid'];
            }
            if ($gatewayTotal === null || $gatewayTotal < $baseAmount) {
                return ['ok' => false, 'code' => 502, 'message' => 'Nominal gateway tidak valid'];
            }

            $payment = Payment::create([
                'uuid' => (string) Str::uuid(),
                'nomor_payment' => $this->orderNumber->nextPayment(),
                'id_order' => $lockedOrder->id,
                'method' => PaymentMethod::QRIS->value,
                'source' => 'paymenku',
                'provider' => 'paymenku',
                'reference' => $reference,
                'transaction_id' => $transactionId,
                'amount' => $baseAmount / 100,
                'base_amount' => $baseAmount / 100,
                'gateway_fee' => ($gatewayTotal - $baseAmount) / 100,
                'gateway_total' => $gatewayTotal / 100,
                'payment_url' => isset($gateway['pay_url']) ? (string) $gateway['pay_url'] : null,
                'expires_at' => $this->parseExpiry($gateway),
                'status' => PaymentStatus::PENDING->value,
                'gateway_transaction_id' => $transactionId,
                'reference_number' => $reference,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $payment->logs()->create([
                'id_payment' => $payment->id,
                'old_status' => null,
                'new_status' => PaymentStatus::PENDING->value,
                'note' => 'Checkout Paymenku dibuat',
                'changed_by' => $user->id,
            ]);

            return [
                'ok' => true,
                'code' => 201,
                'message' => 'Checkout berhasil dibuat',
                'payment' => $payment->fresh(),
                'created' => true,
            ];
        });
    }

    public function applyPaymentEvent(string $payloadHash, array $payload): array
    {
        $transactionId = isset($payload['trx_id']) ? trim((string) $payload['trx_id']) : null;
        $reference = isset($payload['reference_id']) ? trim((string) $payload['reference_id']) : null;
        $status = isset($payload['status']) ? strtolower(trim((string) $payload['status'])) : '';
        $gatewayTotal = $this->amountInCents($payload['amount'] ?? null);

        try {
            return DB::transaction(function () use ($payloadHash, $payload, $transactionId, $reference, $status, $gatewayTotal) {
                $payment = $this->resolvePayment($reference, $transactionId);
                $event = EventPaymentEvent::create([
                    'payment_id' => $payment?->id,
                    'provider' => 'paymenku',
                    'event_type' => isset($payload['event']) ? (string) $payload['event'] : null,
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                    'status' => $status,
                    'gateway_total' => $gatewayTotal === null ? null : $gatewayTotal / 100,
                    'payload_hash' => $payloadHash,
                    'signature_valid' => true,
                ]);

                return $this->processPaymentEvent($event, $payment, $status, $reference, $transactionId, $gatewayTotal);
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return DB::transaction(function () use ($e, $payloadHash, $status, $reference, $transactionId, $gatewayTotal) {
                $event = EventPaymentEvent::where('payload_hash', $payloadHash)->lockForUpdate()->first();
                if (! $event) {
                    throw $e;
                }

                $payment = $this->resolvePayment($reference, $transactionId) ?? $event->payment()->first();
                if ($event->processed_at !== null) {
                    return [
                        'ok' => true,
                        'code' => 200,
                        'message' => 'Event sudah diproses',
                        'duplicate' => true,
                        'payment' => $payment,
                    ];
                }

                if ($payment && $event->payment_id !== $payment->id) {
                    $event->forceFill(['payment_id' => $payment->id])->save();
                }

                return $this->processPaymentEvent($event, $payment, $status, $reference, $transactionId, $gatewayTotal);
            });
        }
    }

    protected function processPaymentEvent(
        EventPaymentEvent $event,
        ?Payment $payment,
        string $status,
        ?string $reference,
        ?string $transactionId,
        ?int $gatewayTotal,
    ): array {
        if (! $payment) {
            $event->forceFill([
                'outcome' => 'unknown_reference',
                'note' => 'Reference pembayaran event tidak dikenal',
                'processed_at' => now(),
            ])->save();

            return ['ok' => true, 'code' => 200, 'message' => 'Reference tidak dikenal'];
        }

        $order = Order::whereKey($payment->id_order)->lockForUpdate()->firstOrFail();
        $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
        $expectedTotal = $this->amountInCents($locked->gateway_total);
        $referenceMatches = $reference !== null && $reference !== '' && hash_equals((string) $locked->reference, $reference);
        $transactionMatches = $transactionId !== null && $transactionId !== '' && hash_equals((string) $locked->transaction_id, $transactionId);
        if (! $referenceMatches || ! $transactionMatches || $expectedTotal === null || $gatewayTotal !== $expectedTotal) {
            $event->forceFill([
                'outcome' => 'mismatch',
                'note' => 'Reference, transaction ID, atau nominal gateway tidak cocok',
                'processed_at' => now(),
            ])->save();

            return ['ok' => false, 'code' => 422, 'message' => 'Data pembayaran tidak cocok'];
        }

        $event->forceFill(['processed_at' => now()])->save();

        if ($status === PaymentStatus::PAID->value) {
            if (in_array($locked->status, [
                PaymentStatus::EXPIRED->value,
                PaymentStatus::CANCELLED->value,
                PaymentStatus::FAILED->value,
            ], true)) {
                $event->forceFill([
                    'outcome' => 'late_paid',
                    'note' => 'Pembayaran terlambat memerlukan rekonsiliasi manual',
                ])->save();
                $this->logPayment($locked, $locked->status, $locked->status, 'late_paid_after_terminal');

                return ['ok' => true, 'code' => 200, 'message' => 'Pembayaran terlambat dicatat untuk rekonsiliasi', 'payment' => $locked->fresh()];
            }

            if ($locked->status === PaymentStatus::PAID->value) {
                $event->forceFill(['outcome' => 'duplicate_paid'])->save();

                return ['ok' => true, 'code' => 200, 'message' => 'Pembayaran sudah diproses', 'payment' => $locked];
            }

            $oldStatus = $locked->status;
            $locked->forceFill([
                'status' => PaymentStatus::PAID->value,
                'paid_at' => $locked->paid_at ?: now(),
                'verified_at' => $locked->verified_at ?: now(),
                'updated_by' => $locked->updated_by,
            ])->save();
            $this->logPayment($locked, $oldStatus, PaymentStatus::PAID->value, 'Pembayaran terverifikasi (Paymenku)');
            $this->payments->syncOrderPaymentStatus($order);

            $freshOrder = $order->fresh();
            if ($this->tickets->canIssue($freshOrder)) {
                $actor = User::whereKey($freshOrder->created_by)->firstOrFail();
                $this->tickets->generate($actor, $freshOrder, 'Pembayaran terverifikasi (Paymenku)');
            }

            $event->forceFill(['outcome' => 'paid'])->save();

            return ['ok' => true, 'code' => 200, 'message' => 'Event diproses', 'payment' => $locked->fresh()];
        }

        if (in_array($status, [
            PaymentStatus::EXPIRED->value,
            PaymentStatus::CANCELLED->value,
            PaymentStatus::FAILED->value,
        ], true)) {
            if ($locked->status !== PaymentStatus::PAID->value) {
                if ($locked->status !== $status) {
                    $oldStatus = $locked->status;
                    $locked->forceFill(['status' => $status])->save();
                    $this->logPayment($locked, $oldStatus, $status, 'Status pembayaran dari Paymenku');
                }
                if ($order->payment_status !== PaymentStatus::PAID->value && $order->payment_status !== $status) {
                    $order->forceFill(['payment_status' => $status])->save();
                }
            }
            $event->forceFill(['outcome' => $locked->status === PaymentStatus::PAID->value ? 'ignored_after_paid' : $status])->save();

            return ['ok' => true, 'code' => 200, 'message' => 'Event diproses', 'payment' => $locked->fresh()];
        }

        $event->forceFill(['outcome' => 'ignored_status'])->save();

        return ['ok' => true, 'code' => 200, 'message' => 'Status diabaikan', 'payment' => $locked];
    }

    protected function resolvePayment(?string $reference, ?string $transactionId): ?Payment
    {
        if ($reference !== null && $reference !== '') {
            $payment = Payment::where('provider', 'paymenku')->where('reference', $reference)->first();
            if ($payment) {
                return $payment;
            }
        }

        if ($transactionId !== null && $transactionId !== '') {
            return Payment::where('provider', 'paymenku')->where('transaction_id', $transactionId)->first();
        }

        return null;
    }

    protected function logPayment(Payment $payment, ?string $oldStatus, string $newStatus, string $note): void
    {
        $payment->logs()->create([
            'id_payment' => $payment->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'changed_by' => null,
        ]);
    }

    protected function amountInCents($amount): ?int
    {
        if (is_int($amount)) {
            return $amount >= 0 && $amount <= 9999999999 ? $amount * 100 : null;
        }
        if (is_float($amount)) {
            $scaled = $amount * 100;
            if (! is_finite($scaled) || $amount < 0 || $amount > 9999999999 || abs($scaled - round($scaled)) > 0.000001) {
                return null;
            }

            return (int) round($scaled);
        }
        if (! is_string($amount)) {
            return null;
        }

        $amount = trim($amount);
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $amount)) {
            return null;
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    protected function parseExpiry(array $gateway): ?Carbon
    {
        $value = $gateway['expires_at'] ?? $gateway['expired_at'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['19', '1062', '23000', '23505'], true)
            || in_array((int) ($e->errorInfo[1] ?? 0), [19, 1062], true);
    }
}
