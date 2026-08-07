<?php

namespace App\Services;

use App\Contracts\DashboardServiceInterface;
use App\DTO\DashboardFilter;
use App\DTO\OverviewKpis;
use App\DTO\PaymentSummary;
use App\DTO\RegistrationSummary;
use App\DTO\RevenueSummary;
use App\Queries\DashboardQuery;

/**
 * Dashboard read model implementation (Sprint 5A).
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
        $data = $this->query->overview($filter->start, $filter->end);

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
        $data = $this->query->registration($filter->start, $filter->end);

        return new RegistrationSummary(
            totalOrders: $data['total_orders'],
            byStatus: $data['by_status'],
        );
    }

    public function revenueSummary(DashboardFilter $filter): RevenueSummary
    {
        $data = $this->query->revenue($filter->start, $filter->end);

        return new RevenueSummary(
            totalRevenue: $data['total_revenue'],
            totalPaid: $data['total_paid'],
            outstanding: $data['outstanding'],
            byStatus: $data['by_status'],
        );
    }

    public function paymentSummary(DashboardFilter $filter): PaymentSummary
    {
        $data = $this->query->payments($filter->start, $filter->end);

        return new PaymentSummary(
            byStatus: $data['by_status'],
            waitingVerification: $data['waiting_verification'],
        );
    }
}