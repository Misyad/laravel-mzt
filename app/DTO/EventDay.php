<?php

namespace App\DTO;

/**
 * Aggregate read model for one event on the Phase 2D event-day operations
 * overview. Present counts come strictly from `prisensi_kehadiran` rows with
 * `id_ticket IS NOT NULL` (Phase 2C); legacy rows are kept labelled separately
 * and never merged into the present figure.
 */
class EventDay
{
    public function __construct(
        public readonly int $id_event = 0,
        public readonly string $judul_event = '',
        public readonly ?string $tanggal_start = null,
        public readonly ?string $lokasi = null,
        public readonly ?int $kuota = null,
        public readonly int $present_count = 0,
        public readonly int $legacy_count = 0,
        public readonly int $gate_count = 0,
        public readonly ?string $latest_tgl = null,
    ) {
    }
}