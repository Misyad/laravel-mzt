<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\EventPaymentService;
use Illuminate\Http\Request;

class EventPaymentController extends Controller
{
    public function checkout(Request $request, EventPaymentService $payments, string $uuid)
    {
        $order = Order::where('uuid', $uuid)->first();
        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
        }

        $result = $payments->checkout($request->user(), $order);
        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => ['payment' => $result['payment']],
        ], $result['code']);
    }
}
