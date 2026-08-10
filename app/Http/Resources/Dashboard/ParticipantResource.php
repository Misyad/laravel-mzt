<?php

namespace App\Http\Resources\Dashboard;

use App\DTO\ParticipantResult;
use App\Support\Operational;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms a ParticipantResult DTO into a paginated participant payload.
 *
 * PII gating (PHASE2D_IMPLEMENTATION_PLAN.md §10): member names AND member ids
 * (`id_anggota`) are only included for callers holding `viewParticipantPII`;
 * otherwise both are nulled while aggregate/identity-neutral fields remain.
 * The DTO always carries full rows; this resource is the single place that
 * applies the PII cut.
 */
class ParticipantResource extends JsonResource
{
    public function toArray($request): array
    {
        $result = $this->resource instanceof ParticipantResult
            ? $this->resource
            : new ParticipantResult();

        $showPii = $request->user() !== null
            && $request->user()->can('viewParticipantPII', Operational::class);

        return [
            'rows' => collect($result->rows)->map(function (array $row) use ($showPii) {
                return [
                    'id' => $row['id'] ?? 0,
                    'id_event' => $row['id_event'] ?? 0,
                    'id_tanggal' => $row['id_tanggal'] ?? null,
                    'id_anggota' => $showPii ? ($row['id_anggota'] ?? '') : null,
                    'nama' => $showPii ? ($row['nama'] ?? null) : null,
                    'source' => $row['source'] ?? 'legacy',
                    'account_status' => $row['account_status'] ?? 'normal',
                    'ticket_status' => $row['ticket_status'] ?? null,
                    'gate' => $row['gate'] ?? null,
                    'scanned_at' => $row['scanned_at'] ?? null,
                ];
            })->all(),
            'meta' => [
                'total' => $result->total,
                'page' => $result->page,
                'per_page' => $result->per_page,
                'last_page' => $result->lastPage(),
                'filter' => $result->filter,
            ],
        ];
    }
}