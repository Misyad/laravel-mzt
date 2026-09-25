<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an Order from an authenticated alumni user (Phase 2A).
 *
 * Responsibilities:
 *  - Validate registration is allowed (delegates to EventCapacityService).
 *  - Reject duplicate registration for the same event (UNIQUE id_event+id_anggota).
 *  - Take an immutable snapshot of the event into the order (S3).
 *  - Assign UUID (S1) and admin order number.
 *  - For free events (total_amount == 0) a ticket is issued right away
 *    (PRD §10.3), wired through TicketService.
 */
class RegistrationService
{
    public function __construct(
        protected EventCapacityService $capacity,
        protected OrderNumberService $orderNumber,
        protected TicketService $tickets,
        protected EventPaymentService $eventPayments,
    ) {}

    /**
     * Register the given user for the given event.
     *
     * @return array{ok: bool, order?: \App\Models\Order, message?: string, code: int}
     */
    public function register(User $user, int $eventId, string $paymentChoice = 'pay_now'): array
    {
        if (! in_array($paymentChoice, ['pay_now', 'pay_at_venue'], true)) {
            return ['ok' => false, 'message' => 'Pilihan pembayaran tidak valid', 'code' => 422];
        }

        // Pre-checks that do not need a lock (visibility, window) are done
        // outside the critical section for fast-fail. Capacity is re-checked
        // inside the lock so concurrent requests cannot both pass.
        $event = Event::find($eventId);
        if (! $event) {
            return ['ok' => false, 'message' => 'Event tidak ditemukan', 'code' => 404];
        }

        $quick = $this->capacity->assertRegistrable($event);
        if (! $quick['ok'] && ! str_contains($quick['message'] ?? '', 'Kuota')) {
            return $quick;
        }

        try {
            $result = DB::transaction(function () use ($user, $eventId, $paymentChoice) {
                // Critical section: serialize on the event row.
                $event = Event::where('id', $eventId)->lockForUpdate()->first();
                if (! $event) {
                    return ['ok' => false, 'message' => 'Event tidak ditemukan', 'code' => 404];
                }

                $check = $this->capacity->assertRegistrable($event);
                if (! $check['ok']) {
                    return $check;
                }

                $exists = Order::where('id_event', $event->id)
                    ->where('id_anggota', $user->id_anggota)
                    ->exists();
                if ($exists) {
                    return ['ok' => false, 'message' => 'Anda sudah mendaftar untuk event ini', 'code' => 409];
                }

                $order = Order::create([
                    'uuid' => Str::uuid()->toString(),
                    'nomor_order' => $this->orderNumber->next(),
                    'id_event' => $event->id,
                    'id_anggota' => $user->id_anggota,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                    'event_name' => $event->judul_event,
                    'event_price' => $event->harga_amount ?: 0,
                    'event_start_at' => $event->tanggal_mulai,
                    'total_amount' => $event->harga_amount ?: 0,
                    'status_registrasi' => OrderStatus::REGISTERED->value,
                    'payment_status' => PaymentStatus::PENDING->value,
                    'payment_choice' => $paymentChoice,
                ]);

                $ticket = null;
                if ($this->tickets->canIssue($order)) {
                    $issued = $this->tickets->generate($user, $order);
                    $ticket = $issued['ticket'] ?? null;
                }

                return ['ok' => true, 'order' => $order, 'ticket' => $ticket, 'message' => 'Registrasi berhasil', 'code' => 201];
            });

        } catch (\Illuminate\Database\QueryException $e) {
            // Unique (id_event, id_anggota) violated by a concurrent winner.
            if (str_contains($e->getMessage(), 'Duplicate entry') || ($e->errorInfo[1] ?? null) === 1062) {
                return ['ok' => false, 'message' => 'Anda sudah mendaftar untuk event ini', 'code' => 409];
            }
            throw $e;
        }

        if (! $result['ok'] || $paymentChoice !== 'pay_now' || (float) $result['order']->total_amount <= 0.001) {
            return $result;
        }

        $checkout = $this->eventPayments->checkout($user, $result['order']);
        if ($checkout['ok']) {
            $result['payment'] = $checkout['payment'];
        } else {
            $result['checkout_error'] = $checkout['message'];
        }

        return $result;
    }
}
