<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\OverviewKpis;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the OverviewKpis DTO into the dashboard overview JSON payload.
 *
 * Pure mapping only: no queries, no business transformation, no recalculation.
 */
class OverviewResource extends JsonResource
{
    public function toArray($request): array
    {
        $kpis = $this->resource instanceof OverviewKpis
            ? $this->resource
            : new OverviewKpis();

        return [
            'total_orders' => $kpis->total_orders,
            'total_revenue' => $kpis->total_revenue,
            'total_paid' => $kpis->total_paid,
            'total_outstanding' => $kpis->total_outstanding,
            'total_tickets' => $kpis->total_tickets,
            'pending_verifications' => $kpis->pending_verifications,
        ];
    }
}