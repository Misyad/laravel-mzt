<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\OperationalSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the OperationalSummary DTO into JSON. Pure mapping only.
 */
class OperationalSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $summary = $this->resource instanceof OperationalSummary
            ? $this->resource
            : new OperationalSummary();

        return [
            'total_orders' => $summary->total_orders,
            'total_paid' => $summary->total_paid,
            'outstanding' => $summary->outstanding,
            'waiting_verification' => $summary->waiting_verification,
            'total_tickets' => $summary->total_tickets,
        ];
    }
}
