<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\GateMonitoring;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the GateMonitoring DTO into the Phase 2D gate payload.
 * Pure mapping only.
 */
class GateMonitoringResource extends JsonResource
{
    public function toArray($request): array
    {
        $monitoring = $this->resource instanceof GateMonitoring
            ? $this->resource
            : new GateMonitoring();

        return [
            'event_id' => $monitoring->event_id,
            'tanggal_id' => $monitoring->tanggal_id,
            'rows' => $monitoring->rows,
            'breakdown_per_gate' => $monitoring->breakdown_per_gate,
        ];
    }
}