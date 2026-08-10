<?php

namespace App\DTO;

/**
 * Pure value object for the Phase 2D participant drill-down filter.
 *
 * Carries only filter state and is agnostic of the HTTP layer (same rule as
 * DashboardFilter). Mapping an incoming Request into this DTO is the
 * responsibility of the OperationalController, never the DTO.
 */
class ParticipantFilter
{
    public function __construct(
        public readonly ?int $eventId = null,
        public readonly ?int $tanggalId = null,
        public readonly ?string $gate = null,
        public readonly ?string $q = null,
        public readonly int $page = 1,
        public readonly int $perPage = 25,
    ) {
    }

    /**
     * The SQL offset for the current page.
     */
    public function offset(): int
    {
        return max($this->page - 1, 0) * $this->perPage;
    }
}