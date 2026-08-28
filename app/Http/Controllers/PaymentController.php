<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\CreatePaymentRequest;
use App\Http\Requests\UploadPaymentProofRequest;
use App\Http\Requests\VerifyPaymentRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Services\PaymentVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Payment Engine API (PRD §21.6).
 *
 * Endpoints:
 *  - POST /api/orders/{uuid}/payment  : upload proof (owner/staff)
 *  - GET  /api/payments/{uuid}        : payment detail (owner/staff)
 *  - PUT  /api/payments/{uuid}/verify : approve/reject (Finance/Admin)
 *  - GET  /api/my-payments            : my payment history
 *  - POST /api/payments               : create payment (staff)
 */
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $payment,
        protected PaymentVerificationService $verification,
    ) {
    }

    /**
     * Create a payment for an order (staff / panitia).
     */
    public function store(CreatePaymentRequest $request)
    {
        $order = Order::where('uuid', $request->order_uuid)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('create', $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $result = $this->payment->create($request->user(), $order, $request->only([
            'method', 'amount', 'reference_number', 'paid_at', 'note',
        ]));

        if (!$result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json(['success' => true, 'data' => $result['payment']], $result['code']);
    }

    /**
     * Upload a payment proof to an order.
     */
    public function upload(UploadPaymentProofRequest $request, $orderUuid)
    {
        $order = Order::where('uuid', $orderUuid)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('upload', $order)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $result = $this->payment->uploadProof(
            $request->user(),
            $order,
            $request->file('payment_proof'),
            $request->bank_name,
            $request->account_name,
            $request->notes,
        );

        if (!$result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json(['success' => true, 'data' => $result['payment']], $result['code']);
    }

    /**
     * Payment detail.
     */
    public function show(Request $request, $uuid)
    {
        $payment = Payment::where('uuid', $uuid)->with(['proofs'])->first();
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('view', $payment)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $outstanding = $this->payment->outstanding($payment->order);

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $payment,
                'outstanding' => $outstanding,
            ],
        ]);
    }

    /**
     * Verify (approve/reject) a payment. Finance/Admin only, idempotent.
     */
    public function verify(VerifyPaymentRequest $request, $uuid)
    {
        $payment = Payment::where('uuid', $uuid)->first();
        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('verify', $payment)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $result = $this->verification->verify(
            $payment,
            $request->user(),
            $request->status,
            $request->note,
        );

        if (!$result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $result['payment'],
                'changed' => $result['changed'],
            ],
            'message' => $result['message'],
        ], $result['code']);
    }

    /**
     * Serve the latest proof file for a payment (plan §5.10).
     * The file itself is stored under storage/app/payments (never public).
     */
    public function proof(Request $request, $uuid)
    {
        $payment = Payment::where('uuid', $uuid)->with(['proofs'])->first();
        if (!$payment || $payment->proofs->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Bukti tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('view', $payment)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $proof = $payment->proofs->last();
        $disk = Storage::disk('local');

        if (!$disk->exists($proof->file_path)) {
            return response()->json(['success' => false, 'message' => 'File bukti tidak tersedia'], 404);
        }

        return $disk->response($proof->file_path, null, [
            'Content-Disposition' => 'inline; filename="' . $proof->original_name . '"',
        ]);
    }

    /**
     * Verification queue — paginated, filterable. Verifier only.
     * Default status = waiting_verification.
     */
    public function index(Request $request)
    {
        if (! Gate::forUser($request->user())->allows('viewQueue', Payment::class)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $request->validate([
            'status' => ['nullable', 'string', Rule::in(PaymentStatus::values())],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = $request->input('status') ?? PaymentStatus::WAITING_VERIFICATION->value;
        $eventId = $request->input('event_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $q = trim((string) $request->input('q', ''));
        $perPage = (int) ($request->input('per_page', 15));
        $perPage = max(1, min(50, $perPage));

        $query = Payment::query()
            ->with([
                'order:id,uuid,nomor_order,event_name,event_price,total_amount,id_event,payment_status',
                'order.event:id,judul_event,slug',
                'proofs:id,id_payment,file_path,original_name,file_size',
            ])
            ->where('status', $status)
            ->when($eventId, fn ($qq) => $qq->whereHas('order', fn ($oq) => $oq->where('id_event', $eventId)))
            ->when($dateFrom, fn ($qq) => $qq->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($qq) => $qq->whereDate('created_at', '<=', $dateTo))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('nomor_payment', 'like', "%{$q}%")
                        ->orWhere('uuid', 'like', "%{$q}%")
                        ->orWhereHas('order', fn ($oq) => $oq->where('nomor_order', 'like', "%{$q}%")
                            ->orWhere('event_name', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        return response()->json(['success' => true, 'data' => $paginator]);
    }

    /**
     * My payment history.
     */
    public function myPayments(Request $request)
    {
        $user = $request->user();
        $payments = Payment::whereHas('order', function ($q) use ($user) {
            $q->where('id_anggota', $user->id_anggota);
        })->with(['order' => fn ($q) => $q->select('id', 'uuid', 'nomor_order', 'event_name', 'event_price', 'total_amount', 'status_registrasi', 'payment_status')])
            ->with('proofs')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json(['success' => true, 'data' => $payments]);
    }
}