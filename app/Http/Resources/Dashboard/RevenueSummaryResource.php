<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\RevenueSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the RevenueSummary DTO into JSON. Pure mapping only.
 */
class RevenueSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $summary = $this->resource instanceof RevenueSummary
            ? $this->resource
            : new RevenueSummary();

        return [
            'total_revenue' => $summary->totalRevenue,
            'total_paid' => $summary->totalPaid,
            'outstanding' => $summary->outstanding,
            'by_status' => $summary->byStatus,
        ];
    }
}