<?php

namespace App\DTO;

/**
 * Registration summary grouped by registration status (Sprint 5A, read-only).
 */
class RegistrationSummary
{
    /**
     * @param  array<int, array{status: string, count: int}>  $byStatus
     */
    public function __construct(
        public readonly int $totalOrders = 0,
        public readonly array $byStatus = [],
    ) {
    }
}