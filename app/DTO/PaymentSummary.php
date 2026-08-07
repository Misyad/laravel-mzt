<?php

namespace App\DTO;

/**
 * Payment summary grouped by payment status (Sprint 5A, read-only).
 */
class PaymentSummary
{
    /**
     * @param  array<int, array{status: string, total: float, count: int}>  $byStatus
     */
    public function __construct(
        public readonly array $byStatus = [],
        public readonly int $waitingVerification = 0,
    ) {
    }
}