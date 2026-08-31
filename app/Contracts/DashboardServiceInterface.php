<?php

namespace App\Contracts;

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

/**
 * Contract for the Dashboard read model (Sprint 5A / 5B.1 / Phase 2D).
 *
 * The Dashboard is a pure Read Model: it only reads data and never performs
 * writes. Implementations must never be depend on services that carry side
 * effects (PaymentService, TicketService, CommunicationDispatcher, ...).
 */
interface DashboardServiceInterface
{
    public function overview(DashboardFilter $filter): OverviewKpis;

    public function registrationSummary(DashboardFilter $filter): RegistrationSummary;

    public function revenueSummary(DashboardFilter $filter): RevenueSummary;

    public function paymentSummary(DashboardFilter $filter): PaymentSummary;

    public function ticketSummary(DashboardFilter $filter): TicketSummary;

    public function operationalSummary(DashboardFilter $filter): OperationalSummary;

    /**
     * Event-day operations overview, one aggregate row per event.
     */
    public function operationalEvents(DashboardFilter $filter): array;

    /**
     * Paginated participant list for one event, with present/legacy source split.
     */
    public function participants(ParticipantFilter $filter): ParticipantResult;

    /**
     * Attendance summary for an event/day, present defined by `id_ticket IS NOT NULL`.
     */
    public function attendanceSummary(DashboardFilter $filter): AttendanceSummary;

    /**
     * Gate monitoring for an event/day, grouped by gate.
     */
    public function gateMonitoring(DashboardFilter $filter): GateMonitoring;

    /**
     * Unified audit timeline (read-only).
     *
     * Combines PaymentLog, TicketLog, and check-in events into a single
     * chronologically-ordered timeline for operators/verifiers.
     *
     * Filter params (all optional):
     *   event_id, date_from, date_to, entity_type, action, actor, q
     *
     * Pagination: server-side, bounded (perPage max 100).
     *
     * Authorization: requires 'view_audit_log' gate.
     */
    public function auditTimeline(DashboardFilter $filter): \Illuminate\Pagination\LengthAwarePaginator;
}