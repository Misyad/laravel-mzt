<?php

namespace App\DTO;

/**
 * Gate monitoring read model for an event / day (Phase 2D).
 *
 * Rows group `prisensi_kehadiran` by `gate`. Present / legacy split follows the
 * canonical definition (present = `id_ticket IS NOT NULL`); gates without any
 * scan are represented by the singular aggregate row `gate` = null (ungated).
 */
class GateMonitoring
{
    public function __construct(
        public readonly int $event_id = 0,
        public readonly ?int $tanggal_id = null,
        public readonly array $rows = [],
        public readonly array $breakdown_per_gate = [],
    ) {
    }
}