<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\EventPaymentService;
use App\Services\KtaPrintRequestService;
use App\Services\PaymenkuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paymenku webhook receiver (PRD v3.0 §11).
 *
 * POST /api/webhooks/paymenku
 *
 * - Verifies the HMAC-SHA256 signature over `timestamp + "." + raw_body`
 *   (constant-time) with a timestamp tolerance window.
 * - Processes events idempotently (payload hash + request row lock).
 * - On a valid `paid` event, atomically marks the payment paid and advances the
 *   KTA request to `menunggu_cetak`. No manual verification step exists.
 *
 * This endpoint is exempt from CSRF/stateful session handling and is not
 * authenticated by Sanctum — authenticity comes from the signature alone.
 */
class PaymenkuWebhookController extends Controller
{
    public function __construct(
        protected PaymenkuService $paymenku,
        protected KtaPrintRequestService $requests,
        protected EventPaymentService $eventPayments,
    ) {}

    public function handle(Request $request)
    {
        // Raw body is required for the signature; do not use $request->all().
        $raw = $request->getContent();
        $signature = $request->header('X-PaymenKu-Signature');
        $timestamp = $request->header('X-PaymenKu-Timestamp');

        if (! $this->paymenku->verifyWebhookSignature($raw, $signature, $timestamp)) {
            // Never log the body (may contain customer data).
            Log::warning('paymenku.webhook.invalid_signature');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $hash = hash('sha256', $raw);
        $reference = isset($payload['reference_id']) ? (string) $payload['reference_id'] : '';

        if (str_starts_with($reference, 'EVENT-')) {
            $result = $this->eventPayments->applyPaymentEvent($hash, $payload);
        } elseif (str_starts_with($reference, 'KTA-')) {
            $result = $this->requests->applyPaymentEvent($hash, $payload);
        } else {
            return response()->json(['received' => true], 200);
        }

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        // Always 200 for recognised/duplicate events so Paymenku stops retrying.
        return response()->json(['received' => true], 200);
    }
}
