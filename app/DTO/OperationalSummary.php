<?php

namespace App\DTO;

/**
 * Cross-entity operational summary (Sprint 5B.1, read-only).
 *
 * Aggregates Orders / Payments / Tickets / verification state into one
 * lightweight read model. Defaults to zero so empty datasets stay successful.
 */
class OperationalSummary
{
    public function __construct(
        public readonly int $total_orders = 0,
        public readonly float $total_paid = 0.0,
        public readonly float $outstanding = 0.0,
        public readonly int $waiting_verification = 0,
        public readonly int $total_tickets = 0,
    ) {
    }
}
