<?php

namespace App\Http\Controllers;

use App\Contracts\DashboardServiceInterface;
use App\DTO\DashboardFilter;
use App\DTO\ParticipantFilter;
use App\Http\Resources\Dashboard\AttendanceSummaryResource;
use App\Http\Resources\Dashboard\GateMonitoringResource;
use App\Http\Resources\Dashboard\OperationalEventsResource;
use App\Http\Resources\Dashboard\ParticipantResource;
use App\Support\Operational;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Phase 2D — EMS Operational Management API (read-only).
 *
 * Thin controller: maps the Request into a filter DTO, enforces module-level
 * authorization via Gate (OperationalPolicy), calls the DashboardServiceInterface
 * and returns the result wrapped in an API Resource. No business logic lives here.
 *
 * Endpoints (all inside auth:sanctum):
 *  - GET /dashboard/operations/events       (viewOperational) — params: start, end, event_id
 *  - GET /dashboard/operations/events/{event}/attendees  (viewOperational; PII gated in Resource)
 *  - GET /dashboard/operations/events/{event}/attendance (viewOperational)
 *  - GET /dashboard/operations/events/{event}/gates      (viewOperational)
 *
 * Note — `status` is intentionally NOT part of the operational event-overview
 * contract. The event overview carries no per-event status semantics, so the
 * param is not accepted here (decision recorded post review; not invented).
 */
class OperationalController extends Controller
{
    public function __construct(
        protected DashboardServiceInterface $dashboard,
    ) {
    }

    public function events(Request $request)
    {
        Gate::forUser($request->user())->authorize('viewOperational', Operational::class);

        $rows = $this->dashboard->operationalEvents($this->map($request));

        return response()->json([
            'success' => true,
            'data' => OperationalEventsResource::collection($rows),
        ]);
    }

    public function attendees(Request $request, $event)
    {
        Gate::forUser($request->user())->authorize('viewOperational', Operational::class);

        $filter = $this->mapParticipant($request, (int) $event);
        $result = $this->dashboard->participants($filter);

        return response()->json([
            'success' => true,
            'data' => new ParticipantResource($result),
        ]);
    }

    public function attendance(Request $request, $event)
    {
        Gate::forUser($request->user())->authorize('viewOperational', Operational::class);

        $filter = $this->map($request)->withEvent((int) $event);
        $summary = $this->dashboard->attendanceSummary($filter);

        return response()->json([
            'success' => true,
            'data' => new AttendanceSummaryResource($summary),
        ]);
    }

    public function gates(Request $request, $event)
    {
        Gate::forUser($request->user())->authorize('viewOperational', Operational::class);

        $filter = $this->map($request)->withEvent((int) $event);
        $monitoring = $this->dashboard->gateMonitoring($filter);

        return response()->json([
            'success' => true,
            'data' => new GateMonitoringResource($monitoring),
        ]);
    }

    /**
     * Map an incoming Request into a DashboardFilter DTO.
     */
    private function map(Request $request): DashboardFilter
    {
        return new DashboardFilter(
            start: $request->input('start') ?: null,
            end: $request->input('end') ?: null,
            eventId: $request->input('event_id') !== null ? (int) $request->input('event_id') : null,
            tanggalId: $request->input('tgl') !== null ? (int) $request->input('tgl') : null,
        );
    }

    /**
     * Map an incoming Request into a ParticipantFilter DTO.
     */
    private function mapParticipant(Request $request, int $eventId): ParticipantFilter
    {
        return new ParticipantFilter(
            eventId: $eventId,
            tanggalId: $request->input('tgl') !== null ? (int) $request->input('tgl') : null,
            gate: $request->input('gate') ?: null,
            q: $request->input('q') ?: null,
            page: max((int) $request->input('page', 1), 1),
            perPage: min(max((int) $request->input('per_page', 25), 1), 100),
        );
    }
}