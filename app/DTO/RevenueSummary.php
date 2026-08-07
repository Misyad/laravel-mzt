<?php

namespace App\DTO;

/**
 * Revenue summary over an optional period (Sprint 5A, read-only).
 */
class RevenueSummary
{
    /**
     * @param  array<int, array{status: string, total: float, count: int}>  $byStatus
     */
    public function __construct(
        public readonly float $totalRevenue = 0.0,
        public readonly float $totalPaid = 0.0,
        public readonly float $outstanding = 0.0,
        public readonly array $byStatus = [],
    ) {
    }
}