<?php

namespace App\Enums;

/**
 * Ticket status (S2: VARCHAR + constants, not a DB ENUM).
 *
 * Matches PRD §16.8 / §10.2 lifecycle:
 * Draft → Issued → Checked In → Finished, or Cancelled / Revoked.
 */
enum TicketStatus: string
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case CHECKED_IN = 'checked_in';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';
    case REVOKED = 'revoked';

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