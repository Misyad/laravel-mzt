<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\TicketSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the TicketSummary DTO into JSON. Pure mapping only.
 * Status values are the canonical ADR-011 values, passed through untouched.
 */
class TicketSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $summary = $this->resource instanceof TicketSummary
            ? $this->resource
            : new TicketSummary();

        return [
            'total_tickets' => $summary->total_tickets,
            'by_status' => $summary->byStatus,
        ];
    }
}
