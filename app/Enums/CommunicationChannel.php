<?php

namespace App\Enums;

/**
 * Outbound communication channels (ADR-016 / PRD §20.6).
 *
 * Sprint 4 only implements IN_APP for real; external channels are represented
 * by this enum for forward compatibility and are delivered through Null/Stub
 * providers (never send a string-literal channel anywhere — rule 1).
 */
enum CommunicationChannel: string
{
    case IN_APP = 'in-app';

    // Reserved for future provider layers — not sent yet (Null/Stub).
    case EMAIL = 'email';
    case WHATSAPP = 'whatsapp';
    case TELEGRAM = 'telegram';
    case DISCORD = 'discord';
    case PUSH = 'push';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}