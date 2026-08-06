<?php

namespace App\Enums;

/**
 * Payment status (S2: VARCHAR + constants, not a DB ENUM).
 *
 * Matches PRD §9: Pending → Waiting Verification → Paid / Rejected / Refund.
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case WAITING_VERIFICATION = 'waiting_verification';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case REFUND = 'refund';

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
