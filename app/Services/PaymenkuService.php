<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin Paymenku REST client (PRD v3.0 §8/§11).
 *
 * Docs: https://docs.paymenku.com
 *   POST /transaction/create   (Bearer API key, optional Idempotency-Key)
 *   GET  /transaction/{trx_id}
 *
 * Only the documented fields are used. Failures are reported as return arrays
 * so callers can map them to HTTP responses; raw secrets/API keys are never
 * logged.
 */
class PaymenkuService
{
    /**
     * Create a payment transaction.
     *
     * @param  array{
     *     channel_code: string,
     *     amount: int,
     *     reference_id: string,
     *     customer_name: string,
     *     customer_email: string,
     *     return_url: string,
     *     customer_phone?: string|null,
     * }  $payload
     * @return array{ok: bool, code: int, message?: string, data?: array}
     */
    public function createTransaction(array $payload, ?string $idempotencyKey = null): array
    {
        $base = rtrim((string) config('paymenku.base_url'), '/');
        $key = (string) config('paymenku.api_key');

        if ($key === '') {
            return ['ok' => false, 'code' => 503, 'message' => 'Payment gateway tidak dikonfigurasi'];
        }

        $headers = ['Accept' => 'application/json'];
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        try {
            $res = Http::withToken($key)
                ->withHeaders($headers)
                ->timeout((int) config('paymenku.timeout', 20))
                ->post($base . '/transaction/create', $payload);
        } catch (\Throwable $e) {
            Log::warning('paymenku.create.exception', ['type' => get_class($e)]);
            return ['ok' => false, 'code' => 502, 'message' => 'Gagal menghubungi payment gateway'];
        }

        $json = $res->json();
        if (! is_array($json) || ($json['status'] ?? null) !== 'success' || ! isset($json['data'])) {
            // Never log the payload (customer data) or headers (token).
            Log::warning('paymenku.create.rejected', ['http' => $res->status()]);
            return [
                'ok' => false,
                'code' => $res->status() === 422 ? 422 : 502,
                'message' => is_array($json) && isset($json['message']) ? (string) $json['message'] : 'Transaksi pembayaran ditolak',
            ];
        }

        return ['ok' => true, 'code' => 200, 'data' => (array) $json['data']];
    }

    /**
     * Server-side status verification (never trust the browser redirect).
     *
     * @return array{ok: bool, code: int, message?: string, data?: array}
     */
    public function checkStatus(string $trxId): array
    {
        $base = rtrim((string) config('paymenku.base_url'), '/');
        $key = (string) config('paymenku.api_key');

        if ($key === '' || $trxId === '') {
            return ['ok' => false, 'code' => 503, 'message' => 'Payment gateway tidak dikonfigurasi'];
        }

        try {
            $res = Http::withToken($key)
                ->acceptJson()
                ->timeout((int) config('paymenku.timeout', 20))
                ->get($base . '/transaction/' . urlencode($trxId));
        } catch (\Throwable $e) {
            Log::warning('paymenku.status.exception', ['type' => get_class($e)]);
            return ['ok' => false, 'code' => 502, 'message' => 'Gagal menghubungi payment gateway'];
        }

        $json = $res->json();
        if (! is_array($json) || ($json['status'] ?? null) !== 'success' || ! isset($json['data'])) {
            return ['ok' => false, 'code' => 502, 'message' => 'Status transaksi tidak tersedia'];
        }

        return ['ok' => true, 'code' => 200, 'data' => (array) $json['data']];
    }

    /**
     * Validate a webhook signature.
     *
     * Official formula (docs.paymenku.com/events/webhooks):
     *   signature = HMAC-SHA256(timestamp + "." + raw_body, webhook_secret)
     * Headers: X-PaymenKu-Signature, X-PaymenKu-Timestamp.
     *
     * Uses a constant-time comparison. Stale timestamps are rejected to blunt
     * replay of captured requests.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature, ?string $timestamp): bool
    {
        $secret = (string) config('paymenku.webhook_secret');
        if ($secret === '' || $signature === null || $timestamp === null || $timestamp === '') {
            return false;
        }

        $tolerance = (int) config('paymenku.webhook_tolerance', 300);
        if (! ctype_digit($timestamp)) {
            return false;
        }
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);

        return hash_equals($expected, strtolower(trim($signature)));
    }
}
