<?php

namespace App\Enums;

/**
 * Order registration status (S2: VARCHAR + constants, not a DB ENUM).
 *
 * Independent from payment status (PRD §7).
 */
enum OrderStatus: string
{
    case DRAFT = 'draft';
    case REGISTERED = 'registered';
    case CONFIRMED = 'confirmed';
    case CHECKED_IN = 'checked_in';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';

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
