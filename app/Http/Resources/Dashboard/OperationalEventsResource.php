<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\EventDay;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms an EventDay DTO into a Phase 2D event operations row.
 * Present count is strictly `id_ticket IS NOT NULL`; legacy kept labelled.
 */
class OperationalEventsResource extends JsonResource
{
    public function toArray($request): array
    {
        $day = $this->resource instanceof EventDay
            ? $this->resource
            : new EventDay();

        return [
            'id_event' => $day->id_event,
            'judul_event' => $day->judul_event,
            'tanggal_start' => $day->tanggal_start,
            'lokasi' => $day->lokasi,
            'kuota' => $day->kuota,
            'present_count' => $day->present_count,
            'legacy_count' => $day->legacy_count,
            'gate_count' => $day->gate_count,
            'latest_tgl' => $day->latest_tgl,
        ];
    }
}