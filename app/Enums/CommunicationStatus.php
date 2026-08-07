<?php

namespace App\Enums;

/**
 * Lifecycle status of a Communication Log (PRD §20.13 / ADR-016).
 *
 * Immutable progression (rule 3): QUEUED → PROCESSING → DELIVERED | FAILED.
 * Never reuse a string literal for log status — always this enum.
 */
enum CommunicationStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}