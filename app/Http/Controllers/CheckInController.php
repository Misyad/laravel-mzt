<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckInRequest;
use App\Services\CheckInService;
use Illuminate\Http\Request;

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

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'id_event' => ['required', 'integer'],
            'id_tanggal' => ['required', 'integer'],
        ]);

        return $this->respond($this->checkIn->lookup(
            $request->user(),
            trim($validated['identifier']),
            (int) $validated['id_event'],
            (int) $validated['id_tanggal'],
        ));
    }

    public function onsite(Request $request)
    {
        $validated = $request->validate([
            'ticket_uuid' => ['required', 'uuid'],
            'id_tanggal' => ['required', 'integer'],
            'gate' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        return $this->respond($this->checkIn->admitOnsite(
            $request->user(),
            $validated['ticket_uuid'],
            (int) $validated['id_tanggal'],
            $validated['gate'] ?? null,
            (float) $validated['amount'],
        ));
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

    protected function respond(array $result)
    {
        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['code']);
    }
}
