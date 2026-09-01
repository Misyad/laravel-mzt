<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Contracts\DashboardServiceInterface;
use App\DTO\AuditTimelineItem;
use App\DTO\DashboardFilter;
use App\Queries\DashboardQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class C_AuditTimeline extends Controller
{
    protected $query;

    public function __construct(DashboardQuery $query)
    {
        $this->query = $query;
    }

    public function index()
    {
        Gate::forUser(request()->user())->authorize('view_audit_log');
        return view('admin.audit_timeline');
    }

    public function data(Request $request)
    {
        Gate::forUser(request()->user())->authorize('view_audit_log');

        $filter = new DashboardFilter(
            eventId: $request->input('event_id') !== null ? (int) $request->input('event_id') : null,
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
            entityType: $request->input('entity_type'),
            action: $request->input('action'),
            actor: $request->input('actor'),
            q: $request->input('q'),
            page: max((int) $request->input('page', 1), 1),
            perPage: min(max((int) $request->input('per_page', 20), 1), 100),
        );

        $data = $this->query->auditTimeline(
            $filter->eventId,
            $filter->dateFrom,
            $filter->dateTo,
            $filter->entityType,
            $filter->action,
            $filter->actor,
            $filter->q,
        );

        $items = collect($data['rows'])->map(fn ($item) => [
            'actor' => $item->actor,
            'action' => $item->action,
            'entity' => $item->entity,
            'entity_id' => $item->entity_id,
            'old_status' => $item->old_status,
            'new_status' => $item->new_status,
            'timestamp' => $item->timestamp->format('Y-m-d H:i:s'),
            'note' => $item->note,
        ]);

        return response()->json([
            'draw' => $request->draw ?? 1,
            'recordsTotal' => $data['total'],
            'recordsFiltered' => $data['total'],
            'data' => $items->values()->all(),
        ], 200);
    }
}