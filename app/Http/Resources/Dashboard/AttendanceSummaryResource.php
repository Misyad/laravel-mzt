<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\AttendanceSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the AttendanceSummary DTO into the Phase 2D attendance payload.
 * Pure mapping only; present/legacy split is preserved as-is.
 */
class AttendanceSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $summary = $this->resource instanceof AttendanceSummary
            ? $this->resource
            : new AttendanceSummary();

        return [
            'event_id' => $summary->event_id,
            'tanggal_id' => $summary->tanggal_id,
            'present' => $summary->present,
            'legacy_count' => $summary->legacy_count,
            'total' => $summary->total,
            'per_tanggal' => $summary->per_tanggal,
        ];
    }
}