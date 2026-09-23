<?php

namespace App\Http\Controllers;

use App\Services\KtaPrintRequestService;
use Illuminate\Http\Request;

class OwnKtaPrintRequestController extends Controller
{
    public function show(Request $request, KtaPrintRequestService $requests)
    {
        $latest = $requests->latestForUser((int) $request->user()->id);

        return $this->response($latest?->toMemberArray());
    }

    public function store(Request $request, KtaPrintRequestService $requests)
    {
        if (! config('kta.print.enabled', false)) {
            return response()->json(['success' => false, 'message' => 'Layanan tidak tersedia'], 503);
        }

        $request->validate([
            'delivery_method' => ['prohibited'],
            'recipient_name' => ['prohibited'],
            'recipient_phone' => ['prohibited'],
            'shipping_address' => ['prohibited'],
        ]);
        $result = $requests->createForUser($request->user());
        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        $payment = $requests->ensurePayment($request->user(), $result['request']);
        if (! $payment['ok']) {
            return response()->json([
                'success' => false,
                'message' => $payment['message'],
                'data' => ['request' => $payment['request']->toMemberArray()],
            ], $payment['code']);
        }

        return $this->response($payment['request']->toMemberArray(), $result['created'] ? 201 : 200);
    }

    protected function response(?array $request, int $status = 200)
    {
        return response()->json([
            'success' => true,
            'data' => ['request' => $request],
        ], $status)->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
