<?php

namespace App\DTO;

/**
 * Ticket monitoring summary grouped by the canonical ADR-011 status
 * (Sprint 5B.1, read-only).
 *
 * Status values follow the canonical ticket state machine directly
 * (draft / issued / checked_in / finished / cancelled / revoked) with no
 * new status mapping. Every field defaults to zero so the dashboard still
 * returns a successful payload on an empty dataset.
 */
class TicketSummary
{
    /**
     * @param  array<int, array{status: string, count: int}>  $byStatus
     */
    public function __construct(
        public readonly int $total_tickets = 0,
        public readonly array $byStatus = [],
    ) {
    }
}
