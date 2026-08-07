<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\PaymentSummary;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the PaymentSummary DTO into JSON. Pure mapping only.
 */
class PaymentSummaryResource extends JsonResource
{
    public function toArray($request): array
    {
        $summary = $this->resource instanceof PaymentSummary
            ? $this->resource
            : new PaymentSummary();

        return [
            'by_status' => $summary->byStatus,
            'waiting_verification' => $summary->waitingVerification,
        ];
    }
}