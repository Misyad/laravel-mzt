<?php

namespace App\Enums;

/**
 * Payment method (S2: VARCHAR + constants, not a DB ENUM).
 *
 * Supported methods for Phase 2B (PRD §16.5 / §13). "Gateway" is reserved for
 * a future phase (PRD §15) and is intentionally not listed here.
 */
enum PaymentMethod: string
{
    case TRANSFER = 'transfer';
    case CASH = 'cash';
    case QRIS = 'qris';
    case SPONSOR = 'sponsor';
    case COMPLIMENTARY = 'complimentary';

    /**
     * All values as a list (useful for validation rules).
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}