<?php

namespace App\Http\Controllers\Public;

use App\Enums\KtaPrintStatus;
use App\Http\Controllers\Controller;
use App\Models\KtaPrintRequest;
use App\Models\User;
use App\Services\KtaChallengeService;
use App\Services\KtaPrintRequestService;
use App\Services\KtaPrintTokenService;
use App\Services\PaymenkuService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        protected PaymenkuService $paymenku,
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

        $data = $request->validate([
            'delivery_method' => ['required', 'string', Rule::in(['pickup', 'delivery'])],
            'recipient_name' => ['nullable', 'string', 'max:100'],
            'recipient_phone' => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['delivery_method'] === 'delivery') {
            if (trim((string) ($data['recipient_name'] ?? '')) === ''
                || trim((string) ($data['recipient_phone'] ?? '')) === ''
                || trim((string) ($data['shipping_address'] ?? '')) === '') {
                return response()->json(['success' => false, 'message' => 'Alamat pengiriman wajib diisi'], 422);
            }
        }

        /** @var User $user */
        $user = User::find($guard['user_id']);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak valid'], 401);
        }

        $result = $this->requests->createForUser($user, $data);
        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        /** @var KtaPrintRequest $req */
        $req = $result['request'];

        // Only create a gateway transaction for a freshly created request that
        // has no transaction yet. Re-submits return the existing row untouched.
        if ($result['created'] || $req->payment_trx_id === null) {
            $payment = $this->createGatewayTransaction($user, $req);
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
     * Create the Paymenku transaction for a request and persist gateway fields.
     *
     * @return array{ok: bool, code: int, message?: string, request?: KtaPrintRequest}
     */
    protected function createGatewayTransaction(User $user, KtaPrintRequest $req): array
    {
        $amount = (int) config('kta.print.amount', 25000);
        $reference = 'KTA-' . $req->id;

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

        $res = $this->paymenku->createTransaction($payload, $reference);

        if (! $res['ok']) {
            return ['ok' => false, 'code' => $res['code'], 'message' => $res['message'], 'request' => $req->fresh()];
        }

        $d = $res['data'];
        $trxId = (string) ($d['trx_id'] ?? '');
        $payUrl = isset($d['pay_url']) ? (string) $d['pay_url'] : null;
        $gatewayAmount = isset($d['amount']) ? (float) $d['amount'] : (float) $amount;

        if ($trxId === '') {
            return ['ok' => false, 'code' => 502, 'message' => 'Gateway tidak mengembalikan trx_id', 'request' => $req->fresh()];
        }

        $updated = $this->requests->attachPayment($req, $reference, $trxId, $gatewayAmount, $payUrl, $payload['channel_code']);

        return ['ok' => true, 'code' => 200, 'request' => $updated];
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
