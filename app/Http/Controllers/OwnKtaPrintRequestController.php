<?php

namespace App\Http\Controllers;

use App\Services\KtaPrintRequestService;
use Illuminate\Http\Request;

class OwnKtaPrintRequestController extends Controller
{
    public function show(Request $request, KtaPrintRequestService $requests)
    {
        $latest = $requests->latestForUser((int) $request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'request' => $latest?->toMemberArray(),
            ],
        ])->withHeaders([
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
