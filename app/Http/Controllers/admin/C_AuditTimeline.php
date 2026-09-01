<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Contracts\DashboardServiceInterface;
use App\DTO\AuditTimelineItem;
use App\DTO\DashboardFilter;
use App\Queries\DashboardQuery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class C_AuditTimeline extends Controller
{
    protected $query;

    public function __construct(DashboardQuery $query)
    {
        $this->query = $query;
    }

    public function index()
    {
        Gate::forUser(request()->user())->authorize('viewAuditLog', \App\Support\Operational::class);
        return view('admin.audit_timeline');
    }

    public function data(Request $request)
    {
        Gate::forUser(request()->user())->authorize('viewAuditLog', \App\Support\Operational::class);

        $eventId = $request->input('event_id') !== null && $request->input('event_id') !== '' ? (int) $request->input('event_id') : null;
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $entityType = $request->input('entity_type');
        $action = $request->input('action');
        $actor = $request->input('actor');
        $q = $request->input('q');

        $data = $this->query->auditTimeline(
            $eventId,
            $dateFrom,
            $dateTo,
            $entityType,
            $action,
            $actor,
            $q,
        );

        $items = collect($data['rows'])->map(fn ($item) => [
            'actor' => $item->actor,
            'action' => $item->action,
            'entity' => $item->entity,
            'entity_id' => $item->entity_id,
            'old_status' => $item->old_status,
            'new_status' => $item->new_status,
            'timestamp' => $item->timestamp instanceof \DateTimeInterface ? $item->timestamp->format('Y-m-d H:i:s') : (string) $item->timestamp,
            'note' => $item->note,
        ]);

        // Keep DataTables keys for legacy but also expose M-05 contract rows/total
        return response()->json([
            'success' => true,
            'data' => [
                'rows' => $items->values()->all(),
                'total' => $data['total'],
                'data' => $items->values()->all(),
                'recordsTotal' => $data['total'],
                'recordsFiltered' => $data['total'],
            ],
            'draw' => $request->input('draw', 1),
        ], 200);
    }
}