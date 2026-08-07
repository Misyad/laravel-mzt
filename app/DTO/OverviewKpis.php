<?php

namespace App\DTO;

/**
 * KPI snapshot for the Finance Dashboard Overview (Sprint 5A, read-only).
 *
 * Plain read model value object: every field defaults to zero so the Dashboard
 * still returns a successful payload on an empty dataset.
 */
class OverviewKpis
{
    public function __construct(
        public readonly int $total_orders = 0,
        public readonly float $total_revenue = 0.0,
        public readonly float $total_paid = 0.0,
        public readonly float $total_outstanding = 0.0,
        public readonly int $total_tickets = 0,
        public readonly int $pending_verifications = 0,
    ) {
    }
}