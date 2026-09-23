<?php

namespace App\Http\Controllers;

use App\Services\KtaPriceService;
use App\Support\RoleGuard;
use Illuminate\Http\Request;

class KtaPriceSettingController extends Controller
{
    public function show(Request $request, KtaPriceService $prices)
    {
        if (! RoleGuard::isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'setting' => $prices->setting(),
                'history' => $prices->history(),
            ],
        ]);
    }

    public function update(Request $request, KtaPriceService $prices)
    {
        if (! RoleGuard::isAdmin($request->user())) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:0'],
        ]);
        $prices->update((int) $data['amount'], $request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'setting' => $prices->setting(),
                'history' => $prices->history(),
            ],
        ]);
    }
}
