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

    /**
     * Create (or return the existing) active print request for a member.
     *
     * Idempotent: a repeated submit returns the same row instead of creating a
     * second one. Race-safe: `active_key` has a unique index, so two concurrent
     * inserts collide and the loser re-reads the winner.
     *
     * @param  array{delivery_method: string, recipient_name?: ?string, recipient_phone?: ?string, shipping_address?: ?string}  $data
     * @return array{ok: bool, code: int, message: string, request?: KtaPrintRequest, created?: bool}
     */
    public function createForUser(User $user, array $data): array
    {
        if ((string) $user->is_active !== '1') {
            return ['ok' => false, 'code' => 403, 'message' => 'Akun anggota tidak aktif'];
        }

        $existing = $this->activeForUser($user->id);
        if ($existing) {
            return ['ok' => true, 'code' => 200, 'message' => 'Pengajuan KTA sudah ada', 'request' => $existing, 'created' => false];
        }

        $method = $data['delivery_method'] === 'delivery' ? 'delivery' : 'pickup';

        try {
            $request = DB::transaction(function () use ($user, $data, $method) {
                $request = KtaPrintRequest::create([
                    'id_users' => $user->id,
                    'id_anggota_snapshot' => (string) $user->id_anggota,
                    'status' => KtaPrintStatus::MENUNGGU_PEMBAYARAN->value,
                    'delivery_method' => $method,
                    'payment_status' => 'pending',
                    'recipient_name' => $method === 'delivery' ? ($data['recipient_name'] ?? null) : null,
                    'recipient_phone' => $method === 'delivery' ? ($data['recipient_phone'] ?? null) : null,
                    'shipping_address' => $method === 'delivery' ? ($data['shipping_address'] ?? null) : null,
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
        $request->forceFill([
            'payment_provider' => 'paymenku',
            'payment_reference' => $reference,
            'payment_trx_id' => $trxId,
            'payment_amount' => $amount,
            'payment_status' => 'pending',
            'pay_url' => $payUrl,
            'payment_channel' => $channel,
        ])->save();

        return $request->fresh();
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

        // Idempotency: a duplicate (hash previously processed) is a no-op.
        $duplicate = KtaPaymentEvent::where('payload_hash', $payloadHash)
            ->whereNotNull('processed_at')
            ->exists();

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

        if ($duplicate) {
            return ['ok' => true, 'code' => 200, 'message' => 'Event sudah diproses', 'duplicate' => true, 'request' => $request];
        }

        if (! $request) {
            $event->forceFill(['processed_at' => now()])->save();
            // Unknown reference: acknowledge so Paymenku stops retrying.
            return ['ok' => true, 'code' => 200, 'message' => 'Reference tidak dikenal'];
        }

        // Amount validation — never trust the client, always compare to the
        // amount we stored when creating the request.
        if ($status === 'paid') {
            $expected = (float) ($request->payment_amount ?? 0);
            $received = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;
            // Paymenku `amount` already includes the fee; accept >= expected.
            if ($expected > 0 && $received + 0.01 < $expected) {
                $event->forceFill(['processed_at' => now()])->save();
                return ['ok' => false, 'code' => 422, 'message' => 'Nominal pembayaran tidak cocok'];
            }
        }

        $result = DB::transaction(function () use ($request, $event, $status, $payload) {
            // Serialize concurrent webhooks on the same request.
            $locked = KtaPrintRequest::where('id', $request->id)->lockForUpdate()->first();

            $event->forceFill(['processed_at' => now()])->save();

            if ($status === 'paid') {
                $this->markPaid($locked, $payload);
            } elseif (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
                $this->markPaymentFailed($locked, $status);
            }

            return $locked->fresh();
        });

        return ['ok' => true, 'code' => 200, 'message' => 'Event diproses', 'request' => $result];
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
        $expected = (float) ($request->payment_amount ?? 0);
        $received = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;

        return $expected > 0 && $received + 0.01 >= $expected;
    }

    /**
     * Admin transition. Returns 422 on an illegal move, 409 on a locked race.
     *
     * @param  array{status: string, reason?: ?string}  $data
     * @return array{ok: bool, code: int, message: string, request?: KtaPrintRequest}
     */
    public function transition(User $actor, KtaPrintRequest $request, string $target, ?string $reason): array
    {
        if (! KtaPrintStateMachine::canTransition($request->status, $target)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Perubahan status tidak valid'];
        }
        if (in_array($target, KtaPrintStateMachine::adminForbiddenTargets(), true)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Perubahan status tidak dapat dilakukan manual'];
        }
        if ($target === KtaPrintStatus::DITOLAK->value && trim((string) $reason) === '') {
            return ['ok' => false, 'code' => 422, 'message' => 'Alasan penolakan wajib diisi'];
        }
        // Pickup/delivery branching must match the chosen delivery method.
        if ($target === KtaPrintStatus::SIAP_DIAMBIL->value && $request->delivery_method !== 'pickup') {
            return ['ok' => false, 'code' => 422, 'message' => 'Request ini menggunakan metode kirim'];
        }
        if ($target === KtaPrintStatus::DIKIRIM->value && $request->delivery_method !== 'delivery') {
            return ['ok' => false, 'code' => 422, 'message' => 'Request ini menggunakan metode ambil'];
        }

        $updated = DB::transaction(function () use ($request, $actor, $target, $reason) {
            $locked = KtaPrintRequest::where('id', $request->id)->lockForUpdate()->first();
            $old = $locked->status;

            if (! KtaPrintStateMachine::canTransition($old, $target)) {
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
            $this->log($request, $old, KtaPrintStatus::PEMBAYARAN_EXPIRED->value, null, 'Pembayaran ' . $status, 'paymenku_webhook');
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
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }
}
