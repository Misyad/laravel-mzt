<?php

namespace App\Services;

use App\Enums\KtaPrintStatus;
use App\Models\KtaPaymentEvent;
use App\Models\KtaPrintRequest;
use App\Models\User;
use App\Support\KtaPrintStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Physical KTA print request workflow (PRD v3.0).
 *
 * Responsibilities:
 *  - create a request for a VERIFIED, ACTIVE member (identity is derived
 *    server-side from the challenge token by the controller — never trusted
 *    from the request body)
 *  - enforce one active request per member, idempotently and race-safely
 *  - advance the request to `menunggu_cetak` atomically when Paymenku reports a
 *    valid `paid` event, inside the same transaction as the event log write
 *  - apply admin transitions through the state machine with audit logging
 */
class KtaPrintRequestService
{
    public function __construct(
        protected KtaPriceService $prices,
        protected PaymenkuService $paymenku,
    ) {
    }

    /**
     * Find the member's active request, if any.
     */
    public function activeForUser(int $userId): ?KtaPrintRequest
    {
        return KtaPrintRequest::query()
            ->where('id_users', $userId)
            ->active()
            ->latest('id')
            ->first();
    }

    public function latestForUser(int $userId): ?KtaPrintRequest
    {
        return KtaPrintRequest::query()
            ->where('id_users', $userId)
            ->latest('id')
            ->first();
    }

    /**
     * Create (or return the existing) active print request for a member.
     *
     * Idempotent: a repeated submit returns the same row instead of creating a
     * second one. Race-safe: `active_key` has a unique index, so two concurrent
     * inserts collide and the loser re-reads the winner.
     *
     * @return array{ok: bool, code: int, message: string, request?: KtaPrintRequest, created?: bool}
     */
    public function createForUser(User $user, array $data = []): array
    {
        if ((string) $user->is_active !== '1') {
            return ['ok' => false, 'code' => 403, 'message' => 'Akun anggota tidak aktif'];
        }

        $existing = $this->activeForUser($user->id);
        if ($existing) {
            return ['ok' => true, 'code' => 200, 'message' => 'Pengajuan KTA sudah ada', 'request' => $existing, 'created' => false];
        }

        try {
            $request = DB::transaction(function () use ($user) {
                $request = KtaPrintRequest::create([
                    'id_users' => $user->id,
                    'id_anggota_snapshot' => (string) $user->id_anggota,
                    'workflow_version' => 2,
                    'status' => KtaPrintStatus::MENUNGGU_PEMBAYARAN->value,
                    'delivery_method' => 'none',
                    'base_amount' => $this->prices->snapshotAmount(),
                    'payment_status' => 'pending',
                    'recipient_name' => null,
                    'recipient_phone' => null,
                    'shipping_address' => null,
                    'submitted_at' => now(),
                    'active_key' => $user->id,
                ]);

                $this->log($request, null, KtaPrintStatus::MENUNGGU_PEMBAYARAN->value, $user->id, 'Anggota mengajukan KTA fisik', 'member');

                return $request;
            });
        } catch (QueryException $e) {
            // Unique active_key collision (concurrent submit) → return the winner.
            if ($this->isUniqueViolation($e)) {
                $winner = $this->activeForUser($user->id);
                if ($winner) {
                    return ['ok' => true, 'code' => 200, 'message' => 'Pengajuan KTA sudah ada', 'request' => $winner, 'created' => false];
                }
            }
            throw $e;
        }

        return ['ok' => true, 'code' => 201, 'message' => 'Pengajuan KTA dibuat', 'request' => $request, 'created' => true];
    }

    /**
     * Attach the Paymenku transaction details to a freshly created request.
     */
    public function attachPayment(
        KtaPrintRequest $request,
        string $reference,
        string $trxId,
        float $amount,
        ?string $payUrl,
        ?string $channel,
    ): KtaPrintRequest {
        return DB::transaction(function () use ($request, $reference, $trxId, $amount, $payUrl, $channel) {
            $locked = KtaPrintRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if ($locked->payment_trx_id !== null) {
                return $locked;
            }

            $baseAmount = (float) ($locked->base_amount ?? 0);
            $locked->forceFill([
                'payment_provider' => 'paymenku',
                'payment_reference' => $reference,
                'payment_trx_id' => $trxId,
                'gateway_fee' => max(0, $amount - $baseAmount),
                'payment_amount' => $amount,
                'payment_status' => 'pending',
                'pay_url' => $payUrl,
                'payment_channel' => $channel,
            ])->save();

            return $locked->fresh();
        });
    }

    public function ensurePayment(User $user, KtaPrintRequest $request): array
    {
        $locked = DB::transaction(fn () => KtaPrintRequest::whereKey($request->id)->lockForUpdate()->firstOrFail());
        if ($locked->payment_trx_id !== null) {
            return ['ok' => true, 'code' => 200, 'request' => $locked];
        }
        if ($locked->base_amount === null) {
            return ['ok' => false, 'code' => 409, 'message' => 'Harga pengajuan tidak tersedia', 'request' => $locked];
        }

        $amount = (int) $locked->base_amount;
        $reference = 'KTA-'.$locked->id;
        if ($amount === 0) {
            $free = DB::transaction(function () use ($locked, $reference) {
                $request = KtaPrintRequest::whereKey($locked->id)->lockForUpdate()->firstOrFail();
                if ($request->payment_status === 'paid') {
                    return $request;
                }

                $old = $request->status;
                $request->forceFill([
                    'status' => KtaPrintStatus::MENUNGGU_CETAK->value,
                    'payment_provider' => 'none',
                    'payment_reference' => $reference,
                    'gateway_fee' => 0,
                    'payment_amount' => 0,
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                ])->save();
                $this->log($request, $old, KtaPrintStatus::MENUNGGU_CETAK->value, null, 'Harga KTA Rp0', 'system');

                return $request->fresh();
            });

            return ['ok' => true, 'code' => 200, 'request' => $free];
        }

        $payload = [
            'channel_code' => (string) config('kta.print.channel_code', 'qris'),
            'amount' => $amount,
            'reference_id' => $reference,
            'customer_name' => (string) $user->name,
            'customer_email' => (string) ($user->email ?: 'anggota@maziltutholiban.org'),
            'return_url' => (string) config('kta.print.return_url', '/cek-kta'),
        ];
        $dataUser = \App\Models\DataUser::where('id_users', $user->id)->first(['no_hp']);
        if ($dataUser && trim((string) $dataUser->no_hp) !== '') {
            $payload['customer_phone'] = (string) $dataUser->no_hp;
        }

        $response = $this->paymenku->createTransaction($payload, $reference);
        if (! $response['ok']) {
            return ['ok' => false, 'code' => $response['code'], 'message' => $response['message'], 'request' => $locked->fresh()];
        }

        $gateway = $response['data'];
        $trxId = (string) ($gateway['trx_id'] ?? '');
        if ($trxId === '') {
            return ['ok' => false, 'code' => 502, 'message' => 'Gateway tidak mengembalikan trx_id', 'request' => $locked->fresh()];
        }

        $totalInCents = $this->amountInCents($gateway['amount'] ?? null);
        if ($totalInCents === null || $totalInCents < $amount * 100) {
            return ['ok' => false, 'code' => 502, 'message' => 'Nominal gateway tidak valid', 'request' => $locked->fresh()];
        }
        $total = $totalInCents / 100;

        return [
            'ok' => true,
            'code' => 200,
            'request' => $this->attachPayment(
                $locked,
                $reference,
                $trxId,
                $total,
                isset($gateway['pay_url']) ? (string) $gateway['pay_url'] : null,
                $payload['channel_code'],
            ),
        ];
    }

    /**
     * Apply a verified Paymenku webhook event.
     *
     * Idempotent: a redelivered payload hash already recorded as processed is
     * ignored and the current request state is returned unchanged. Paid events
     * atomically mark the payment paid and advance the request to
     * `menunggu_cetak` in one DB transaction.
     *
     * @param  array<string, mixed>  $payload  decoded webhook body
     * @return array{ok: bool, code: int, message: string, duplicate?: bool, request?: KtaPrintRequest}
     */
    public function applyPaymentEvent(string $payloadHash, array $payload): array
    {
        $trxId = isset($payload['trx_id']) ? (string) $payload['trx_id'] : null;
        $reference = isset($payload['reference_id']) ? (string) $payload['reference_id'] : null;
        $status = isset($payload['status']) ? strtolower((string) $payload['status']) : '';

        try {
            return DB::transaction(function () use ($payloadHash, $payload, $trxId, $reference, $status) {
                $request = $this->resolveRequest($reference, $trxId);
                $event = KtaPaymentEvent::create([
                    'kta_print_request_id' => $request?->id,
                    'provider' => 'paymenku',
                    'event_type' => isset($payload['event']) ? (string) $payload['event'] : null,
                    'trx_id' => $trxId,
                    'reference_id' => $reference,
                    'status' => $status,
                    'payload_hash' => $payloadHash,
                    'signature_valid' => true,
                ]);

                return $this->processPaymentEvent($event, $request, $status, $payload);
            });
        } catch (QueryException $e) {
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }

            return DB::transaction(function () use ($e, $payloadHash, $payload, $trxId, $reference, $status) {
                $event = KtaPaymentEvent::where('payload_hash', $payloadHash)->lockForUpdate()->first();
                if (! $event) {
                    throw $e;
                }

                $request = $this->resolveRequest($reference, $trxId) ?? $event->request()->first();
                if ($event->processed_at !== null) {
                    return [
                        'ok' => true,
                        'code' => 200,
                        'message' => 'Event sudah diproses',
                        'duplicate' => true,
                        'request' => $request,
                    ];
                }

                if ($request && $event->kta_print_request_id !== $request->id) {
                    $event->forceFill(['kta_print_request_id' => $request->id])->save();
                }

                return $this->processPaymentEvent($event, $request, $status, $payload);
            });
        }
    }

    protected function processPaymentEvent(
        KtaPaymentEvent $event,
        ?KtaPrintRequest $request,
        string $status,
        array $payload,
    ): array {
        if (! $request) {
            $event->forceFill(['processed_at' => now()])->save();

            return ['ok' => true, 'code' => 200, 'message' => 'Reference tidak dikenal'];
        }

        $locked = KtaPrintRequest::where('id', $request->id)->lockForUpdate()->firstOrFail();

        if ($status === 'paid') {
            $expected = $this->amountInCents($locked->payment_amount);
            $received = $this->amountInCents($payload['amount'] ?? null);
            $receivedReference = isset($payload['reference_id']) ? (string) $payload['reference_id'] : '';
            $receivedTrxId = isset($payload['trx_id']) ? (string) $payload['trx_id'] : '';
            $referenceMatches = $receivedReference !== '' && hash_equals((string) $locked->payment_reference, $receivedReference);
            $trxMatches = $receivedTrxId !== '' && hash_equals((string) $locked->payment_trx_id, $receivedTrxId);
            if (! $referenceMatches || ! $trxMatches || $expected === null || $received !== $expected) {
                $event->forceFill(['processed_at' => now()])->save();

                return ['ok' => false, 'code' => 422, 'message' => 'Data pembayaran tidak cocok'];
            }
        }

        $event->forceFill(['processed_at' => now()])->save();

        if ($status === 'paid') {
            if ($locked->status === KtaPrintStatus::PEMBAYARAN_EXPIRED->value) {
                $this->recordLatePaidAfterExpiry($locked, $payload);
            } else {
                $this->markPaid($locked, $payload);
            }
        } elseif (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
            $this->markPaymentFailed($locked, $status);
        }

        return ['ok' => true, 'code' => 200, 'message' => 'Event diproses', 'request' => $locked->fresh()];
    }

    /**
     * Verify the amount for a paid event without mutating state (used by the
     * controller to short-circuit before logging). Kept for symmetry.
     */
    public function amountMatches(?KtaPrintRequest $request, array $payload): bool
    {
        if (! $request) {
            return false;
        }
        $expected = $this->amountInCents($request->payment_amount);
        $received = $this->amountInCents($payload['amount'] ?? null);

        return $expected !== null && $received === $expected;
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

    /**
     * Admin transition. Returns 422 on an illegal move, 409 on a locked race.
     *
     * @param  array{status: string, reason?: ?string}  $data
     * @return array{ok: bool, code: int, message: string, request?: KtaPrintRequest}
     */
    public function transition(User $actor, KtaPrintRequest $request, string $target, ?string $reason): array
    {
        if (! $this->canTransition($request, $target)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Perubahan status tidak valid'];
        }
        if (in_array($target, KtaPrintStateMachine::adminForbiddenTargets(), true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Perubahan status tidak dapat dilakukan manual'];
        }
        if ($target === KtaPrintStatus::DITOLAK->value && trim((string) $reason) === '') {
            return ['ok' => false, 'code' => 422, 'message' => 'Alasan penolakan wajib diisi'];
        }
        if (! $request->isLegacyWorkflow() && in_array($target, [
            KtaPrintStatus::SIAP_DIAMBIL->value,
            KtaPrintStatus::DIKIRIM->value,
        ], true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Status logistik hanya tersedia untuk data lama'];
        }
        if ($request->isLegacyWorkflow() && $target === KtaPrintStatus::SIAP_DIAMBIL->value && $request->delivery_method !== 'pickup') {
            return ['ok' => false, 'code' => 422, 'message' => 'Request ini menggunakan metode kirim'];
        }
        if ($request->isLegacyWorkflow() && $target === KtaPrintStatus::DIKIRIM->value && $request->delivery_method !== 'delivery') {
            return ['ok' => false, 'code' => 422, 'message' => 'Request ini menggunakan metode ambil'];
        }

        $updated = DB::transaction(function () use ($request, $actor, $target, $reason) {
            $locked = KtaPrintRequest::where('id', $request->id)->lockForUpdate()->first();
            $old = $locked->status;

            if (! $this->canTransition($locked, $target)) {
                return null;
            }

            $locked->status = $target;
            $stamps = [
                KtaPrintStatus::SUDAH_DICETAK->value => ['printed_at' => now(), 'printed_by' => $actor->id],
                KtaPrintStatus::SIAP_DIAMBIL->value => ['ready_at' => now()],
                KtaPrintStatus::DIKIRIM->value => ['shipped_at' => now()],
                KtaPrintStatus::SELESAI->value => ['completed_at' => now(), 'completed_by' => $actor->id],
                KtaPrintStatus::DITOLAK->value => ['rejected_at' => now(), 'rejection_reason' => $reason],
            ];
            $locked->forceFill($stamps[$target] ?? [])->save();

            if ($locked->isActive()) {
                // still active — keep the guard key
            } else {
                $locked->forceFill(['active_key' => null])->save();
            }

            $this->log($locked, $old, $target, $actor->id, $reason, 'admin');

            return $locked->fresh();
        });

        if (! $updated) {
            return ['ok' => false, 'code' => 409, 'message' => 'Status berubah, silakan muat ulang'];
        }

        return ['ok' => true, 'code' => 200, 'message' => 'Status diperbarui', 'request' => $updated];
    }

    protected function canTransition(KtaPrintRequest $request, string $target): bool
    {
        if ($request->isLegacyWorkflow() && $request->status === KtaPrintStatus::SUDAH_DICETAK->value) {
            return in_array($target, [
                KtaPrintStatus::SIAP_DIAMBIL->value,
                KtaPrintStatus::DIKIRIM->value,
            ], true);
        }

        return KtaPrintStateMachine::canTransition($request->status, $target);
    }

    protected function recordLatePaidAfterExpiry(KtaPrintRequest $request, array $payload): void
    {
        $request->forceFill([
            'payment_status' => 'paid',
            'paid_at' => $request->paid_at ?: now(),
            'payment_trx_id' => $request->payment_trx_id ?: ($payload['trx_id'] ?? null),
        ])->save();

        $this->log(
            $request,
            KtaPrintStatus::PEMBAYARAN_EXPIRED->value,
            KtaPrintStatus::PEMBAYARAN_EXPIRED->value,
            null,
            'late_paid_after_expiry',
            'paymenku_webhook',
        );
    }

    /**
     * Mark payment paid and advance the request, atomically within the caller's
     * transaction. Idempotent if already paid.
     */
    protected function markPaid(KtaPrintRequest $request, array $payload): void
    {
        if ($request->payment_status === 'paid') {
            return; // redelivery after a successful transition
        }

        $old = $request->status;
        $request->forceFill([
            'payment_status' => 'paid',
            'paid_at' => now(),
            'payment_trx_id' => $request->payment_trx_id ?: ($payload['trx_id'] ?? null),
        ])->save();

        if ($old === KtaPrintStatus::MENUNGGU_PEMBAYARAN->value) {
            $request->status = KtaPrintStatus::MENUNGGU_CETAK->value;
            $request->save();
            $this->log($request, $old, KtaPrintStatus::MENUNGGU_CETAK->value, null, 'Pembayaran terverifikasi (Paymenku)', 'paymenku_webhook');
        }
    }

    /**
     * Mark payment failed/expired/cancelled. Never advances to print queue.
     */
    protected function markPaymentFailed(KtaPrintRequest $request, string $status): void
    {
        if ($request->payment_status === 'paid') {
            return; // already paid; ignore late failure events
        }

        $request->forceFill(['payment_status' => $status])->save();

        if ($request->status === KtaPrintStatus::MENUNGGU_PEMBAYARAN->value) {
            $old = $request->status;
            $request->forceFill([
                'status' => KtaPrintStatus::PEMBAYARAN_EXPIRED->value,
                'active_key' => null,
            ])->save();
            $this->log($request, $old, KtaPrintStatus::PEMBAYARAN_EXPIRED->value, null, 'Pembayaran '.$status, 'paymenku_webhook');
        }
    }

    protected function resolveRequest(?string $reference, ?string $trxId): ?KtaPrintRequest
    {
        if ($reference !== null && $reference !== '') {
            $byRef = KtaPrintRequest::where('payment_reference', $reference)->first();
            if ($byRef) {
                return $byRef;
            }
        }
        if ($trxId !== null && $trxId !== '') {
            return KtaPrintRequest::where('payment_trx_id', $trxId)->first();
        }

        return null;
    }

    protected function log(KtaPrintRequest $request, ?string $old, string $new, ?int $actorId, ?string $reason, string $source): void
    {
        $request->logs()->create([
            'kta_print_request_id' => $request->id,
            'old_status' => $old,
            'new_status' => $new,
            'reason' => $reason,
            'actor_id' => $actorId,
            'source' => $source,
        ]);
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        return in_array((string) $e->getCode(), ['19', '1062', '23000', '23505'], true)
            || in_array((int) ($e->errorInfo[1] ?? 0), [19, 1062], true);
    }
}
