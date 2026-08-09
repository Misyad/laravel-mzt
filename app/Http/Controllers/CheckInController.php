<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckInRequest;
use App\Services\CheckInService;

/**
 * QR Check-in API (PRD §17.8 / §17.12) — Phase 2C.
 *
 * POST /api/checkin — ticket-based attendance. Thin controller: every rule
 * lives in CheckInService (validation chain, duplicate detection, transaction).
 */
class CheckInController extends Controller
{
    public function __construct(
        protected CheckInService $checkIn,
    ) {
    }

    public function store(CheckInRequest $request)
    {
        $result = $this->checkIn->checkIn(
            $request->user(),
            $request->ticket_uuid,
            (int) $request->id_tanggal,
            $request->gate,
        );

        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'data' => $result['data'] ?? null,
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['code']);
    }
}
