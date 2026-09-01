<?php

namespace App\Queries;

use App\DTO\ParticipantFilter;
use App\DTO\AuditTimelineItem;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Prisensi_kehadiran;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * Query layer for the Dashboard read model (Sprint 5A).
 *
 * This is the ONLY place that touches raw database reads for the dashboard. It
 * performs aggregate reads (COUNT / SUM / groupBy) and returns only plain,
 * scalar array values — never Eloquent models, builders, collections or
 * paginators. It instantiates no services and carries no side effects.
 *
 * Partial application of the period filters to a shared underlying query avoids
 * re-scanning (no N+1), keeping each endpoint fast.
 */
class DashboardQuery
{
    private function orderQuery(?string $start, ?string $end, ?int $eventId = null)
    {
        return Order::query()
            ->when($start, fn ($q) => $q->whereDate('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('created_at', '<=', $end))
            ->when($eventId, fn ($q) => $q->where('id_event', $eventId));
    }

    private function paymentQuery(?string $start, ?string $end, ?int $eventId = null, ?string $status = null)
    {
        return Payment::query()
            ->when($start, fn ($q) => $q->whereDate('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('created_at', '<=', $end))
            ->when($eventId, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('id_event', $eventId)))
            ->when($status, fn ($q) => $q->where('status', $status));
    }

    private function ticketQuery(?string $start, ?string $end, ?int $eventId = null, ?string $status = null)
    {
        return Ticket::query()
            ->when($start, fn ($q) => $q->whereDate('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('created_at', '<=', $end))
            ->when($eventId, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('id_event', $eventId)))
            ->when($status, fn ($q) => $q->where('status', $status));
    }

    /**
     * KPI snapshot over orders, payments and tickets.
     *
     * @return array{
     *   total_orders: int,
     *   total_revenue: float,
     *   total_paid: float,
     *   total_outstanding: float,
     *   total_tickets: int,
     *   pending_verifications: int
     * }
     */
    public function overview(?string $start = null, ?string $end = null, ?int $eventId = null, ?string $status = null): array
    {
        $orders = $this->orderQuery($start, $end, $eventId);
        $payments = $this->paymentQuery($start, $end, $eventId, $status);
        $tickets = $this->ticketQuery($start, $end, $eventId, $status);

        $totalOrders = (int) (clone $orders)->count('id');
        $totalRevenue = (float) (clone $orders)->sum('total_amount');

        $totalPaid = (float) (clone $payments)
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');

        $pendingVerifications = (int) (clone $payments)
            ->where('status', PaymentStatus::WAITING_VERIFICATION->value)
            ->count('id');

        $totalTickets = (int) (clone $tickets)->count('id');

        return [
            'total_orders' => $totalOrders,
            'total_revenue' => round($totalRevenue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_outstanding' => round(max($totalRevenue - $totalPaid, 0), 2),
            'total_tickets' => $totalTickets,
            'pending_verifications' => $pendingVerifications,
        ];
    }

    /**
     * Registration counts grouped by registration status.
     *
     * @return array{total_orders: int, by_status: array<int, array{status: string, count: int}>}
     */
    public function registration(?string $start = null, ?string $end = null, ?int $eventId = null, ?string $status = null): array
    {
        $query = $this->orderQuery($start, $end, $eventId)
            ->when($status, fn ($q) => $q->where('status_registrasi', $status));

        $totalOrders = (int) (clone $query)->count('id');

        $rows = (clone $query)
            ->selectRaw('status_registrasi as status, COUNT(*) as count')
            ->groupBy('status_registrasi')
            ->orderBy('status_registrasi')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->count])
            ->all();

        return [
            'total_orders' => $totalOrders,
            'by_status' => $rows,
        ];
    }

    /**
     * Revenue over an optional period.
     *
     * @return array{
     *   total_revenue: float,
     *   total_paid: float,
     *   outstanding: float,
     *   by_status: array<int, array{status: string, total: float, count: int}>
     * }
     */
    public function revenue(?string $start = null, ?string $end = null): array
    {
        $orders = $this->orderQuery($start, $end);
        $payments = $this->paymentQuery($start, $end);

        $totalRevenue = (float) (clone $orders)->sum('total_amount');
        $totalPaid = (float) (clone $payments)
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');

        $byStatus = (clone $payments)
            ->select('status', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'total' => round((float) $r->total, 2), 'count' => (int) $r->count])
            ->all();

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_paid' => round($totalPaid, 2),
            'outstanding' => round(max($totalRevenue - $totalPaid, 0), 2),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Payment sums and counts grouped by payment status.
     *
     * @return array{by_status: array<int, array{status: string, total: float, count: int}>, waiting_verification: int}
     */
    public function payments(?string $start = null, ?string $end = null): array
    {
        $query = $this->paymentQuery($start, $end);

        $byStatus = (clone $query)
            ->select('status', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'total' => round((float) $r->total, 2), 'count' => (int) $r->count])
            ->all();

        $waiting = (int) (clone $query)
            ->where('status', PaymentStatus::WAITING_VERIFICATION->value)
            ->count('id');

        return [
            'by_status' => $byStatus,
            'waiting_verification' => $waiting,
        ];
    }

    /**
     * Ticket monitoring grouped by the canonical ADR-011 ticket status
     * (Sprint 5B.1). Groups directly on the stored `tickets.status` column —
     * no new status mapping is introduced (FR-01).
     *
     * @return array{total_tickets: int, by_status: array<int, array{status: string, count: int}>}
     */
    public function tickets(?string $start = null, ?string $end = null, ?int $eventId = null, ?string $status = null): array
    {
        $query = $this->ticketQuery($start, $end, $eventId, $status);

        $totalTickets = (int) (clone $query)->count('id');

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count])
            ->all();

        return [
            'total_tickets' => $totalTickets,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Cross-entity operational summary (Sprint 5B.1): orders, paid amounts,
     * outstanding, pending verifications and total tickets in a single pass
     * over Orders / Payments / Tickets (no N+1, FR-02).
     *
     * @return array{
     *   total_orders: int,
     *   total_paid: float,
     *   outstanding: float,
     *   waiting_verification: int,
     *   total_tickets: int
     * }
     */
    public function operational(?string $start = null, ?string $end = null, ?int $eventId = null, ?string $status = null): array
    {
        $orders = $this->orderQuery($start, $end, $eventId);
        $payments = $this->paymentQuery($start, $end, $eventId, $status);

        $totalOrders = (int) (clone $orders)->count('id');
        $totalRevenue = (float) (clone $orders)->sum('total_amount');

        $totalPaid = (float) (clone $payments)
            ->where('status', PaymentStatus::PAID->value)
            ->sum('amount');

        $waitingVerifications = (int) (clone $payments)
            ->where('status', PaymentStatus::WAITING_VERIFICATION->value)
            ->count('id');

        $totalTickets = (int) (clone $this->ticketQuery($start, $end, $eventId, $status))->count('id');

        return [
            'total_orders' => $totalOrders,
            'total_paid' => round($totalPaid, 2),
            'outstanding' => round(max($totalRevenue - $totalPaid, 0), 2),
            'waiting_verification' => $waitingVerifications,
            'total_tickets' => $totalTickets,
        ];
    }

    /**
     * Event-day operations overview (Phase 2D): one aggregate row per event.
     *
     * Present count is strictly `prisensi_kehadiran.id_ticket IS NOT NULL`
     * (Phase 2C QR-scan rows); legacy (`id_ticket IS NULL`) is counted
     * separately and labelled, never merged.
     *
     * @return list<array{
     *   id_event: int,
     *   judul_event: string,
     *   tanggal_start: string|null,
     *   lokasi: string|null,
     *   kuota: int|null,
     *   present_count: int,
     *   legacy_count: int,
     *   gate_count: int,
     *   latest_tgl: string|null
     * }>
     */
    public function operationalEvents(?string $start = null, ?string $end = null, ?int $eventId = null): array
    {
        $events = Event::query()
            ->when($start, fn ($q) => $q->whereDate('tanggal_mulai', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('tanggal_mulai', '<=', $end))
            ->when($eventId, fn ($q) => $q->where('id', $eventId))
            ->get(['id', 'judul_event', 'tanggal_mulai', 'lokasi', 'kuota']);

        if ($events->isEmpty()) {
            return [];
        }

        $ids = $events->pluck('id')->all();

        $agg = Prisensi_kehadiran::query()
            ->selectRaw('id_event')
            ->selectRaw('SUM(CASE WHEN id_ticket IS NOT NULL THEN 1 ELSE 0 END) as present_count')
            ->selectRaw('SUM(CASE WHEN id_ticket IS NULL THEN 1 ELSE 0 END) as legacy_count')
            ->selectRaw('COUNT(DISTINCT gate) as gate_count')
            ->selectRaw('MAX(tanggal_kehadiran) as latest_tgl')
            ->whereIn('id_event', $ids)
            ->groupBy('id_event')
            ->get()
            ->keyBy('id_event');

        return $events->map(fn ($e) => [
            'id_event' => (int) $e->id,
            'judul_event' => (string) $e->judul_event,
            'tanggal_start' => $e->tanggal_mulai !== null ? (string) $e->tanggal_mulai : null,
            'lokasi' => $e->lokasi,
            'kuota' => $e->kuota !== null ? (int) $e->kuota : null,
            'present_count' => (int) ($agg[$e->id]->present_count ?? 0),
            'legacy_count' => (int) ($agg[$e->id]->legacy_count ?? 0),
            'gate_count' => (int) ($agg[$e->id]->gate_count ?? 0),
            'latest_tgl' => isset($agg[$e->id]) && $agg[$e->id]->latest_tgl !== null
                ? (string) $agg[$e->id]->latest_tgl
                : null,
        ])->all();
    }

    /**
     * Paginated participant list for one event (Phase 2D).
     *
     * `Prisensi_kehadiran` rows are left-joined to `users` on `id_anggota`
     * (varchar, matching production collation) to resolve member names. Rows
     * without a matching account are kept and flagged `orphan`, never dropped.
     * Source split: `phase2c` (`id_ticket IS NOT NULL`) vs `legacy`. Ticket status
     * is resolved in the same query via a left join to `tickets` (no N+1).
     *
     * @return array{rows: list<array>, total: int}
     */
    public function participants(ParticipantFilter $filter): array
    {
        $query = Prisensi_kehadiran::query()
            ->leftJoin('users', 'users.id_anggota', '=', 'prisensi_kehadiran.id_anggota')
            ->leftJoin('tickets', 'tickets.id', '=', 'prisensi_kehadiran.id_ticket')
            ->where('prisensi_kehadiran.id_event', $filter->eventId)
            ->when($filter->tanggalId, fn ($q) => $q->where('prisensi_kehadiran.id_tanggal', $filter->tanggalId))
            ->when($filter->gate, fn ($q) => $q->where('prisensi_kehadiran.gate', $filter->gate))
            ->when($filter->q, fn ($q) => $q->where(function ($w) use ($filter) {
                $w->where('prisensi_kehadiran.id_anggota', 'like', '%'.$filter->q.'%')
                    ->orWhere('users.name', 'like', '%'.$filter->q.'%');
            }));

        $total = (int) (clone $query)->count('prisensi_kehadiran.id');

        $rows = (clone $query)
            ->select([
                'prisensi_kehadiran.id',
                'prisensi_kehadiran.id_event',
                'prisensi_kehadiran.id_tanggal',
                'prisensi_kehadiran.id_anggota',
                'prisensi_kehadiran.id_ticket',
                'prisensi_kehadiran.gate',
                'prisensi_kehadiran.scanned_at',
                'users.id as user_id',
                'users.name as nama',
                'tickets.status as ticket_status',
            ])
            ->orderByDesc('prisensi_kehadiran.id')
            ->offset($filter->offset())
            ->limit($filter->perPage)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'id_event' => (int) $row->id_event,
                'id_tanggal' => $row->id_tanggal !== null ? (int) $row->id_tanggal : null,
                'id_anggota' => (string) $row->id_anggota,
                'nama' => $row->nama,
                'source' => $row->id_ticket !== null ? 'phase2c' : 'legacy',
                'account_status' => $row->user_id !== null ? 'normal' : 'orphan',
                'ticket_status' => $row->ticket_status,
                'gate' => $row->gate,
                'scanned_at' => $row->scanned_at ? (string) $row->scanned_at : null,
            ])
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Attendance summary for an event/day (Phase 2D).
     *
     * Present = `prisensi_kehadiran.id_ticket IS NOT NULL`; legacy counted
     * separately. `per_tanggal` groups the same split by `id_tanggal`.
     *
     * @return array{
     *   event_id: int,
     *   tanggal_id: int|null,
     *   present: int,
     *   legacy_count: int,
     *   total: int,
     *   per_tanggal: list<array{tanggal_id: int|null, present: int, legacy_count: int}>
     * }
     */
    public function attendanceSummary(?int $eventId = null, ?int $tanggalId = null): array
    {
        $base = Prisensi_kehadiran::query()
            ->where('id_event', $eventId)
            ->when($tanggalId, fn ($q) => $q->where('id_tanggal', $tanggalId));

        $present = (int) (clone $base)->whereNotNull('id_ticket')->count('id');
        $legacyCount = (int) (clone $base)->whereNull('id_ticket')->count('id');

        $perTanggal = Prisensi_kehadiran::query()
            ->where('id_event', $eventId)
            ->when($tanggalId, fn ($q) => $q->where('id_tanggal', $tanggalId))
            ->selectRaw('id_tanggal')
            ->selectRaw('SUM(CASE WHEN id_ticket IS NOT NULL THEN 1 ELSE 0 END) as present')
            ->selectRaw('SUM(CASE WHEN id_ticket IS NULL THEN 1 ELSE 0 END) as legacy_count')
            ->groupBy('id_tanggal')
            ->orderBy('id_tanggal')
            ->get()
            ->map(fn ($r) => [
                'tanggal_id' => $r->id_tanggal !== null ? (int) $r->id_tanggal : null,
                'present' => (int) $r->present,
                'legacy_count' => (int) $r->legacy_count,
            ])
            ->all();

        return [
            'event_id' => (int) $eventId,
            'tanggal_id' => $tanggalId,
            'present' => $present,
            'legacy_count' => $legacyCount,
            'total' => $present + $legacyCount,
            'per_tanggal' => $perTanggal,
        ];
    }

    /**
     * Gate monitoring for an event/day (Phase 2D).
     *
     * Groups `prisensi_kehadiran` by `gate`; rows without a gate value are
     * grouped under `(ungated)`. Present/legacy split follows the canonical
     * definition (`id_ticket IS NOT NULL`).
     *
     * @return array{
     *   event_id: int,
     *   tanggal_id: int|null,
     *   rows: list<array{gate: string|null, present: int, legacy: int, total: int}>,
     *   breakdown_per_gate: array<string, array{present: int, legacy: int, total: int}>
     * }
     */
    public function gateMonitoring(?int $eventId = null, ?int $tanggalId = null): array
    {
        $rows = Prisensi_kehadiran::query()
            ->where('id_event', $eventId)
            ->when($tanggalId, fn ($q) => $q->where('id_tanggal', $tanggalId))
            ->selectRaw("COALESCE(gate, '(ungated)') as gate")
            ->selectRaw('SUM(CASE WHEN id_ticket IS NOT NULL THEN 1 ELSE 0 END) as present')
            ->selectRaw('SUM(CASE WHEN id_ticket IS NULL THEN 1 ELSE 0 END) as legacy_count')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('gate')
            ->orderBy('gate')
            ->get()
            ->map(fn ($r) => [
                'gate' => (string) $r->gate,
                'present' => (int) $r->present,
                'legacy' => (int) $r->legacy_count,
                'total' => (int) $r->total,
            ])
            ->all();

        return [
            'event_id' => (int) $eventId,
            'tanggal_id' => $tanggalId,
            'rows' => $rows,
            'breakdown_per_gate' => collect($rows)->mapWithKeys(
                static fn (array $r) => $r['gate'] !== null
                    ? [$r['gate'] => ['present' => $r['present'], 'legacy' => $r['legacy'], 'total' => $r['total']]]
                    : []
            )->all(),
        ];
    }

    /**
     * Unified audit timeline (read-only).
     *
     * Combines PaymentLog, TicketLog, and check-in events into a single
     * chronologically-ordered timeline for operators/verifiers.
     *
     * Filter params (all optional):
     *   event_id, date_from, date_to, entity_type, action, actor, q
     *
     * @return array{rows: list<AuditTimelineItem>, total: int}
     */
    public function auditTimeline(
        ?int $eventId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $entityType = null,
        ?string $action = null,
        ?string $actor = null,
        ?string $q = null,
    ): array {
        $query = DB::table('payment_logs')
            ->selectRaw("'payment' as entity, payment_logs.*")
            ->leftJoin('payments', 'payments.id', '=', 'payment_logs.id_payment')
            ->leftJoin('orders', 'orders.id', '=', 'payments.id_order');

        $ticketQuery = DB::table('ticket_logs')
            ->selectRaw("'ticket' as entity, ticket_logs.*")
            ->leftJoin('tickets', 'tickets.id', '=', 'ticket_logs.id_ticket');

        // Apply date filters to both
        $query = $this->applyDateFilter($query, $dateFrom, $dateTo);
        $ticketQuery = $this->applyDateFilter($ticketQuery, $dateFrom, $dateTo);

        // Filter by event (through order -> event or ticket -> order -> event)
        if ($eventId !== null) {
            $query = $query->where('orders.id_event', $eventId);
            $ticketQuery = $ticketQuery->leftJoin('orders', 'orders.id', '=', 'tickets.id_order')->where('orders.id_event', $eventId);
        }

        // Filter by entity type
        if ($entityType !== null) {
            $query = $query->where('entity_type', $entityType); // will be handled via case
            $ticketQuery = $ticketQuery->whereRaw('1 = 0'); // disable for now
        }

        // Filter by action
        if ($action !== null) {
            $query = $query->where('action', $action);
            $ticketQuery = $ticketQuery->where('action', $action);
        }

        // Filter by actor
        if ($actor !== null) {
            $query = $query->where('actor', $actor);
            $ticketQuery = $ticketQuery->where('actor', $actor);
        }

        // Filter by search query
        if ($q !== null && $q !== '') {
            $query = $query->where(function ($w) use ($q) {
                $w->where('note', 'like', '%'.$q.'%')
                    ->orWhere('reference_number', 'like', '%'.$q.'%');
            });
            $ticketQuery = $ticketQuery->where(function ($w) use ($q) {
                $w->where('note', 'like', '%'.$q.'%');
            });
        }

        // Union all logs, order by created_at desc, then map to AuditTimelineItem
        // Note: payment_logs/ticket_logs have no action/actor columns; we derive from new_status/changed_by and entity literal.
        $combined = $query->unionAll($ticketQuery)
            ->orderBy('created_at', 'desc')
            ->get();

        $rows = $combined->map(fn ($row) => new AuditTimelineItem(
            actor: (string) ($row->changed_by ?? ''),
            action: (string) ($row->new_status ?? ''),
            entity: (string) ($row->entity ?? 'payment'),
            entity_id: (int) (($row->entity ?? 'payment') === 'payment' ? ($row->id_payment ?? $row->id) : ($row->id_ticket ?? $row->id)),
            old_status: $row->old_status,
            new_status: $row->new_status,
            timestamp: $row->created_at ? new \DateTime($row->created_at) : new \DateTime(),
            note: $row->note,
        ))->all();

        return ['rows' => $rows, 'total' => count($rows)];
    }

    /**
     * Derive entity type from action string.
     */
    private function entityTypeFromAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'payment') => 'payment',
            str_contains($action, 'ticket') => 'ticket',
            default => 'checkin',
        };
    }

    /**
     * Derive entity ID from action and row data.
     */
    private function entityIdFromAction(string $action, $row): int
    {
        return match (true) {
            str_contains($action, 'payment') => (int) $row->id_payment,
            str_contains($action, 'ticket') => (int) $row->id_ticket,
            default => (int) $row->id,
        };
    }

    /**
     * Apply date filters to a query.
     */
    private function applyDateFilter($query, ?string $dateFrom, ?string $dateTo)
    {
        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }
        return $query;
    }
}