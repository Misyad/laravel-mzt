<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\RegistrationSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the RegistrationSummary DTO into JSON. Pure mapping only.
 */
class RegistrationSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $summary = $this->resource instanceof RegistrationSummary
            ? $this->resource
            : new RegistrationSummary();

        return [
            'total_orders' => $summary->totalOrders,
            'by_status' => $summary->byStatus,
        ];
    }
}