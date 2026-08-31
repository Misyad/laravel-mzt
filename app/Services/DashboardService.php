<?php

namespace App\Services;

use App\Contracts\DashboardServiceInterface;
use App\DTO\AuditTimelineItem;
use App\DTO\AttendanceSummary;
use App\DTO\DashboardFilter;
use App\DTO\EventDay;
use App\DTO\GateMonitoring;
use App\DTO\OperationalSummary;
use App\DTO\OverviewKpis;
use App\DTO\ParticipantFilter;
use App\DTO\ParticipantResult;
use App\DTO\PaymentSummary;
use App\DTO\RegistrationSummary;
use App\DTO\RevenueSummary;
use App\DTO\TicketSummary;
use App\Queries\DashboardQuery;

/**
 * Dashboard read model implementation (Sprint 5A / 5B.1 / Phase 2D).
 *
 * This service is pure read: it maps aggregate query results into DTOs and
 * returns only DTOs. It never returns Eloquent models, builders, collections,
 * or paginators, and it never touches services with side effects
 * (PaymentService, TicketService, CommunicationDispatcher, ...). All database
 * access is delegated to the DashboardQuery layer.
 */
class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        protected DashboardQuery $query,
    ) {
    }

    public function overview(DashboardFilter $filter): OverviewKpis
    {
        $data = $this->query->overview($filter->start, $filter->end, $filter->eventId, $filter->status);

        return new OverviewKpis(
            total_orders: $data['total_orders'],
            total_revenue: $data['total_revenue'],
            total_paid: $data['total_paid'],
            total_outstanding: $data['total_outstanding'],
            total_tickets: $data['total_tickets'],
            pending_verifications: $data['pending_verifications'],
        );
    }

    public function registrationSummary(DashboardFilter $filter): RegistrationSummary
    {
        $data = $this->query->registration($filter->start, $filter->end, $filter->eventId, $filter->status);

        return new RegistrationSummary(
            totalOrders: $data['total_orders'],
            byStatus: $data['by_status'],
        );
    }

    public function revenueSummary(DashboardFilter $filter): RevenueSummary
    {
        $data = $this->query->revenue($filter->start, $filter->end, $filter->eventId, $filter->status);

        return new RevenueSummary(
            totalRevenue: $data['total_revenue'],
            totalPaid: $data['total_paid'],
            outstanding: $data['outstanding'],
            byStatus: $data['by_status'],
        );
    }

    public function paymentSummary(DashboardFilter $filter): PaymentSummary
    {
        $data = $this->query->payments($filter->start, $filter->end, $filter->eventId, $filter->status);

        return new PaymentSummary(
            byStatus: $data['by_status'],
            waitingVerification: $data['waiting_verification'],
        );
    }

    public function ticketSummary(DashboardFilter $filter): TicketSummary
    {
        $data = $this->query->tickets($filter->start, $filter->end, $filter->eventId, $filter->status);

        return new TicketSummary(
            total_tickets: $data['total_tickets'],
            byStatus: $data['by_status'],
        );
    }

    public function operationalSummary(DashboardFilter $filter): OperationalSummary
    {
        $data = $this->query->operational($filter->start, $filter->end, $filter->eventId, $filter->status);

        return new OperationalSummary(
            total_orders: $data['total_orders'],
            total_paid: $data['total_paid'],
            outstanding: $data['outstanding'],
            waiting_verification: $data['waiting_verification'],
            total_tickets: $data['total_tickets'],
        );
    }

    public function operationalEvents(DashboardFilter $filter): array
    {
        $rows = $this->query->operationalEvents(
            $filter->start,
            $filter->end,
            $filter->eventId,
        );

        return array_map(fn (array $row) => new EventDay(
            id_event: (int) $row['id_event'],
            judul_event: (string) ($row['judul_event'] ?? ''),
            tanggal_start: $row['tanggal_start'] ?? null,
            lokasi: $row['lokasi'] ?? null,
            kuota: $row['kuota'] !== null ? (int) $row['kuota'] : null,
            present_count: (int) $row['present_count'],
            legacy_count: (int) $row['legacy_count'],
            gate_count: (int) $row['gate_count'],
            latest_tgl: $row['latest_tgl'] ?? null,
        ), $rows);
    }

    public function participants(ParticipantFilter $filter): ParticipantResult
    {
        $data = $this->query->participants($filter);

        return new ParticipantResult(
            rows: $data['rows'],
            total: (int) $data['total'],
            page: $filter->page,
            per_page: $filter->perPage,
            filter: [
                'event_id' => $filter->eventId,
                'tgl' => $filter->tanggalId,
                'gate' => $filter->gate,
                'q' => $filter->q,
            ],
        );
    }

    public function attendanceSummary(DashboardFilter $filter): AttendanceSummary
    {
        $data = $this->query->attendanceSummary($filter->eventId, $filter->tanggalId);

        return new AttendanceSummary(
            event_id: (int) ($data['event_id'] ?? 0),
            tanggal_id: $data['tanggal_id'] ?? null,
            present: (int) $data['present'],
            legacy_count: (int) $data['legacy_count'],
            total: (int) $data['total'],
            per_tanggal: $data['per_tanggal'],
        );
    }

    public function gateMonitoring(DashboardFilter $filter): GateMonitoring
    {
        $data = $this->query->gateMonitoring($filter->eventId, $filter->tanggalId);

        return new GateMonitoring(
            event_id: (int) ($data['event_id'] ?? 0),
            tanggal_id: $data['tanggal_id'] ?? null,
            rows: $data['rows'],
            breakdown_per_gate: $data['breakdown_per_gate'] ?? [],
        );
    }

    public function auditTimeline(DashboardFilter $filter): \Illuminate\Pagination\LengthAwarePaginator
    {
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
            'timestamp' => $item->timestamp,
            'note' => $item->note,
        ]);

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $items->values()->all(),
            (int) $data['total'],
            $filter->perPage ?? 20,
            $filter->page ?? 1,
        );
    }
}