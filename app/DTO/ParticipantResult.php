<?php

namespace App\DTO;

/**
 * Paginated participant list read model (Phase 2D).
 *
 * `rows` are plain arrays. Each row is either the aggregate variant (no PII;
 * returned to staff without the viewParticipantPII ability) or the PII variant
 * (returned to verifiers). The PII gating happens in the Resource layer; this
 * DTO always carries the full dataset and never drops columns itself.
 */
class ParticipantResult
{
    public function __construct(
        public readonly array $rows = [],
        public readonly int $total = 0,
        public readonly int $page = 1,
        public readonly int $per_page = 25,
        public readonly array $filter = [],
    ) {
    }

    /**
     * Total number of pages for the given page size.
     */
    public function lastPage(): int
    {
        return $this->per_page > 0 ? (int) ceil($this->total / $this->per_page) : 0;
    }
}