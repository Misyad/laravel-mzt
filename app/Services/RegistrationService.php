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
    ) {
    }

    /**
     * Register the given user for the given event.
     *
     * @return array{ok: bool, order?: \App\Models\Order, message?: string, code: int}
     */
    public function register(User $user, int $eventId): array
    {
        $event = Event::find($eventId);
        if (!$event) {
            return ['ok' => false, 'message' => 'Event tidak ditemukan', 'code' => 404];
        }

        $check = $this->capacity->assertRegistrable($event);
        if (!$check['ok']) {
            return $check;
        }

        return DB::transaction(function () use ($user, $event) {
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
            ]);

            // Free event → ticket issued immediately (PRD §10.3).
            $ticket = null;
            if ($this->tickets->canIssue($order)) {
                $issued = $this->tickets->generate($user, $order);
                $ticket = $issued['ticket'] ?? null;
            }

            return ['ok' => true, 'order' => $order, 'ticket' => $ticket, 'message' => 'Registrasi berhasil', 'code' => 201];
        });
    }
}