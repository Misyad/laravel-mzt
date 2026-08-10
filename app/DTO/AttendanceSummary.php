<?php

namespace App\DTO;

/**
 * Attendance summary for an event / day (Phase 2D).
 *
 * Present is defined exclusively as `prisensi_kehadiran.id_ticket IS NOT NULL`
 * (Phase 2C QR scan). Legacy rows (`id_ticket IS NULL`) are counted separately
 * and always labelled historical/legacy — never merged into present.
 */
class AttendanceSummary
{
    public function __construct(
        public readonly int $event_id = 0,
        public readonly ?int $tanggal_id = null,
        public readonly int $present = 0,
        public readonly int $legacy_count = 0,
        public readonly int $total = 0,
        public readonly array $per_tanggal = [],
    ) {
    }
}