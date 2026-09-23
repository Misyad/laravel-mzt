<?php

namespace App\Http\Controllers\Public;

use App\Enums\KtaPrintStatus;
use App\Http\Controllers\Controller;
use App\Models\KtaPrintRequest;
use App\Models\User;
use App\Services\KtaChallengeService;
use App\Services\KtaPrintRequestService;
use App\Services\KtaPrintTokenService;
use Illuminate\Http\Request;

/**
 * Public physical KTA print request endpoints (PRD v3.0).
 *
 *   POST /api/public/kta/print-request   — create (or return) an active request
 *   GET  /api/public/kta/print-request   — read the member's active request
 *
 * Identity is derived ONLY from a verified print token (issued by the
 * ownership-verification success path). The body never carries `id_users` or
 * `id_anggota` as proof, and raw PII is never returned.
 *
 * Flow: create request (status menunggu_pembayaran) → create Paymenku
 * transaction → user pays → verified webhook marks paid and atomically moves
 * the request to menunggu_cetak. No manual payment verification exists.
 */
class KtaPrintRequestController extends Controller
{
    public function __construct(
        protected KtaPrintRequestService $requests,
        protected KtaPrintTokenService $printToken,
        protected KtaChallengeService $challenge,
    ) {
    }

    /**
     * Read the member's current active request, if any.
     */
    public function show(Request $request)
    {
        $guard = $this->guard($request);
        if (isset($guard['error'])) {
            return $guard['error'];
        }

        $active = $this->requests->activeForUser($guard['user_id']);

        return $this->noCache(response()->json([
            'success' => true,
            'data' => [
                'request' => $active ? $active->toPublicArray() : null,
            ],
        ], 200));
    }

    /**
     * Create the active request and a Paymenku transaction.
     */
    public function store(Request $request)
    {
        $guard = $this->guard($request);
        if (isset($guard['error'])) {
            return $guard['error'];
        }

        $request->validate([
            'delivery_method' => ['prohibited'],
            'recipient_name' => ['prohibited'],
            'recipient_phone' => ['prohibited'],
            'shipping_address' => ['prohibited'],
        ]);

        /** @var User $user */
        $user = User::find($guard['user_id']);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak valid'], 401);
        }

        $result = $this->requests->createForUser($user);
        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        /** @var KtaPrintRequest $req */
        $req = $result['request'];

        // Only create a gateway transaction for a freshly created request that
        // has no transaction yet. Re-submits return the existing row untouched.
        if ($result['created'] || $req->payment_trx_id === null) {
            $payment = $this->requests->ensurePayment($user, $req);
            if (! $payment['ok']) {
                return response()->json([
                    'success' => false,
                    'message' => $payment['message'],
                    'data' => ['request' => $req->fresh()->toPublicArray()],
                ], $payment['code']);
            }
            $req = $payment['request'];
        }

        return $this->noCache(response()->json([
            'success' => true,
            'data' => ['request' => $req->toPublicArray()],
        ], $result['created'] ? 201 : 200));
    }

    /**
     * Resolve and validate the verified print token.
     *
     * @return array{error?: \Illuminate\Http\JsonResponse, user_id?: int}
     */
    protected function guard(Request $request): array
    {
        if (! config('kta.print.enabled', false)) {
            return ['error' => response()->json(['success' => false, 'message' => 'Layanan tidak tersedia'], 503)];
        }

        $check = $this->printToken->validate(
            (string) $request->input('print_token'),
            $this->challenge->ipHash($request->ip()),
            $this->challenge->agentHash($request->userAgent()),
        );

        if (! $check['valid']) {
            return ['error' => response()->json(['success' => false, 'message' => 'Sesi verifikasi tidak valid'], 401)];
        }

        return ['user_id' => (int) $check['user_id']];
    }

    protected function noCache($response)
    {
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
