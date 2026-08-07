<?php

namespace App\Contracts;

use App\DTO\DashboardFilter;
use App\DTO\OverviewKpis;
use App\DTO\PaymentSummary;
use App\DTO\RegistrationSummary;
use App\DTO\RevenueSummary;

/**
 * Contract for the Dashboard read model (Sprint 5A).
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
}