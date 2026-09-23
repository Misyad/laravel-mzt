<?php

namespace App\Http\Controllers;

use App\Enums\KtaPrintStatus;
use App\Models\KtaPrintRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin KTA print queue (PRD v3.0 §19–§21).
 *
 *   GET  /api/kta/print-requests          — list (queue + filters)
 *   GET  /api/kta/print-requests/{id}     — detail with audit timeline
 *   PUT  /api/kta/print-requests/{id}/status — apply a state transition
 *
 * The production queue only exposes statuses from `menunggu_cetak` onward;
 * `menunggu_pembayaran` is available as an explicit filter but never counts as
 * a production item. Identity is masked in responses.
 */
class KtaPrintRequestController extends Controller
{
    /**
     * List requests with filters.
     */
    public function index(Request $request)
    {
        if (! Gate::forUser($request->user())->allows('viewQueue', KtaPrintRequest::class)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'status' => ['nullable', 'string', Rule::in(KtaPrintStatus::values())],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $request->input('status');
        $q = trim((string) $request->input('q', ''));
        $perPage = max(1, min(50, (int) $request->input('per_page', 15)));

        $query = KtaPrintRequest::query()->with('user:id,name,id_anggota');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        } else {
            // Default view: the production queue only.
            $query->productionQueue();
        }

        $query->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('id_anggota_snapshot', 'like', "%{$q}%")
                        ->orWhere('payment_reference', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => collect($paginator->items())->map(fn (KtaPrintRequest $r) => $this->adminRow($r))->all(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Detail + audit timeline.
     */
    public function show(Request $request, $id)
    {
        $req = KtaPrintRequest::with(['user:id,name,id_anggota'])->find($id);
        if (! $req) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan'], 404);
        }

        if (! Gate::forUser($request->user())->allows('view', $req)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'request' => array_merge($this->adminRow($req), [
                    'recipient_name' => $req->isLegacyWorkflow() ? $req->recipient_name : null,
                    'recipient_phone' => $req->isLegacyWorkflow() ? $req->recipient_phone : null,
                    'shipping_address' => $req->isLegacyWorkflow() ? $req->shipping_address : null,
                    'notes' => $req->notes,
                    'logs' => $req->logs->map(fn ($l) => [
                        'old_status' => $l->old_status,
                        'new_status' => $l->new_status,
                        'reason' => $l->reason,
                        'source' => $l->source,
                        'actor_id' => $l->actor_id,
                        'at' => optional($l->created_at)->toIso8601String(),
                    ])->all(),
                ]),
            ],
        ]);
    }

    /**
     * Apply an admin transition.
     */
    public function updateStatus(Request $request, $id, \App\Services\KtaPrintRequestService $service)
    {
        $req = KtaPrintRequest::find($id);
        if (! $req) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan'], 404);
        }

        if (! Gate::forUser($request->user())->allows('process', $req)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(KtaPrintStatus::values())],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->transition($request->user(), $req, $data['status'], $data['reason'] ?? null);

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'success' => true,
            'data' => ['request' => $this->adminRow($result['request'])],
        ], 200);
    }

    /**
     * Masked admin projection.
     *
     * @return array<string, mixed>
     */
    protected function adminRow(KtaPrintRequest $r): array
    {
        $name = (string) ($r->user->name ?? '');
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $masked = implode(' ', array_map(fn ($w) => $w === '' ? '' : mb_substr($w, 0, 1) . '***', $words));
        $idAnggota = (string) ($r->id_anggota_snapshot ?? '');

        return [
            'id' => $r->id,
            'reference' => 'KTA-' . $r->id,
            'nama_masked' => $masked,
            'id_anggota_masked' => $idAnggota === '' ? '' : 'MZT***' . mb_substr($idAnggota, -3),
            'status' => $r->status,
            'delivery_method' => $r->isLegacyWorkflow() ? $r->delivery_method : null,
            'base_amount' => $r->base_amount,
            'gateway_fee' => $r->gateway_fee,
            'payment_status' => $r->payment_status,
            'payment_amount' => $r->payment_amount,
            'submitted_at' => optional($r->submitted_at)->toIso8601String(),
            'updated_at' => optional($r->updated_at)->toIso8601String(),
        ];
    }
}
