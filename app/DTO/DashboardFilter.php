<?php

namespace App\DTO;

/**
 * Pure value object for Dashboard read filters (Sprint 5A).
 *
 * Intentionally free of any framework dependency: it carries filter state only
 * and is agnostic of the HTTP layer. Mapping an incoming Request to this DTO is
 * the responsibility of the Controller (or a dedicated factory), never the DTO.
 */
class DashboardFilter
{
    public function __construct(
        public readonly ?string $start = null,
        public readonly ?string $end = null,
        public readonly ?int $eventId = null,
        public readonly ?string $status = null,
    ) {
    }
}