<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TicketStatus;
use App\Events\TicketStatusChanged;
use App\Models\DataUser;
use App\Models\Event;
use App\Models\Order;
use App\Models\Prisensi_kehadiran;
use App\Models\Tanggal_event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CheckInService
{
    public function __construct(
        protected PaymentService $payments,
    ) {}

    public function lookup(
        User $actor,
        string $identifier,
        int $idEvent,
        int $idTanggal,
        string $identifierType = 'ticket'
    ): array {
        if (! $this->canCheckIn($actor)) {
            return ['ok' => false, 'message' => 'Forbidden', 'code' => 403];
        }

        $eventValidation = $this->validateEvent($idEvent);
        if (! $eventValidation['ok']) {
            return $eventValidation;
        }

        $resolved = $identifierType === 'member_card'
            ? $this->resolveMemberCard($identifier, $idEvent)
            : $this->resolveTicket($identifier);

        if (! $resolved['ok']) {
            return $resolved;
        }

        $ticket = $resolved['ticket'];
        $validation = $this->validate($actor, $ticket, $idTanggal, $idEvent, false);
        if (! $validation['ok']) {
            return $validation;
        }

        if (! in_array($ticket->status, [TicketStatus::ISSUED->value, TicketStatus::CHECKED_IN->value], true)) {
            return ['ok' => false, 'message' => 'Tiket tidak dapat digunakan pada status saat ini', 'code' => 409];
        }

        return [
            'ok' => true,
            'message' => 'Peserta ditemukan',
            'code' => 200,
            'data' => $this->scannerPayload($ticket, $validation['order']),
        ];
    }

    public function checkIn(User $actor, string $ticketUuid, int $idTanggal, ?string $gate): array
    {
        if (! $this->canCheckIn($actor)) {
            return ['ok' => false, 'message' => 'Forbidden', 'code' => 403];
        }

        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        if (! $ticket) {
            return ['ok' => false, 'message' => 'Tiket tidak ditemukan', 'code' => 404];
        }

        $validation = $this->validate($actor, $ticket, $idTanggal, null, false);
        if (! $validation['ok']) {
            return $validation;
        }

        $result = DB::transaction(function () use ($actor, $ticket, $idTanggal, $gate) {
            $order = Order::whereKey($ticket->id_order)->lockForUpdate()->first();
            if (! $order) {
                return ['ok' => false, 'message' => 'Data pesanan tidak ditemukan', 'code' => 422];
            }

            $orderValidation = $this->validateOrder($order);
            if (! $orderValidation['ok']) {
                return $orderValidation;
            }

            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->first();
            if (! $lockedTicket) {
                return ['ok' => false, 'message' => 'Tiket tidak ditemukan', 'code' => 404];
            }

            if ($lockedTicket->status === TicketStatus::CHECKED_IN->value) {
                return $this->duplicate($lockedTicket);
            }

            if ($lockedTicket->status !== TicketStatus::ISSUED->value) {
                return ['ok' => false, 'message' => 'Tiket tidak dapat digunakan pada status saat ini', 'code' => 409];
            }

            $outstanding = $this->payments->outstanding($order);
            if (! $this->isPaid($order, $outstanding)) {
                return ['ok' => false, 'message' => 'Pembayaran belum lunas', 'code' => 409];
            }

            $attendance = $this->recordAttendance($actor, $lockedTicket, $order, $idTanggal, $gate);

            return ['ok' => true, 'attendance' => $attendance, 'ticket' => $lockedTicket, 'order' => $order];
        });

        if (! $result['ok']) {
            return $result;
        }

        return [
            'ok' => true,
            'message' => 'Check-in berhasil',
            'code' => 200,
            'data' => $this->checkInPayload(
                $result['ticket']->fresh(),
                $result['order'],
                $result['attendance']
            ),
        ];
    }

    public function admitOnsite(User $actor, string $ticketUuid, int $idTanggal, ?string $gate, float $confirmedAmount): array
    {
        if (! $this->canCheckIn($actor)) {
            return ['ok' => false, 'message' => 'Forbidden', 'code' => 403];
        }

        $ticket = Ticket::where('uuid', $ticketUuid)->first();
        if (! $ticket) {
            return ['ok' => false, 'message' => 'Tiket tidak ditemukan', 'code' => 404];
        }

        $validation = $this->validate($actor, $ticket, $idTanggal, null, false);
        if (! $validation['ok']) {
            return $validation;
        }

        return DB::transaction(function () use ($actor, $ticket, $idTanggal, $gate, $confirmedAmount) {
            $order = Order::whereKey($ticket->id_order)->lockForUpdate()->first();
            if (! $order) {
                return ['ok' => false, 'message' => 'Data pesanan tidak ditemukan', 'code' => 422];
            }

            $orderValidation = $this->validateOrder($order);
            if (! $orderValidation['ok']) {
                return $orderValidation;
            }

            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->first();
            if (! $lockedTicket) {
                return ['ok' => false, 'message' => 'Tiket tidak ditemukan', 'code' => 404];
            }

            if ($lockedTicket->status === TicketStatus::CHECKED_IN->value) {
                return $this->duplicate($lockedTicket);
            }

            if ($lockedTicket->status !== TicketStatus::ISSUED->value) {
                return ['ok' => false, 'message' => 'Tiket tidak dapat digunakan pada status saat ini', 'code' => 409];
            }

            if ($order->payment_choice !== 'pay_at_venue') {
                return ['ok' => false, 'message' => 'Order tidak menggunakan pembayaran di tempat', 'code' => 409];
            }

            $summary = $this->payments->outstanding($order);
            if ($this->isPaid($order, $summary)) {
                return ['ok' => false, 'message' => 'Pembayaran sudah lunas', 'code' => 409];
            }
            $outstanding = $summary['outstanding'];

            if ($this->amountInCents($confirmedAmount) !== $this->amountInCents($outstanding)) {
                return ['ok' => false, 'message' => 'Nominal pembayaran tidak sesuai sisa tagihan', 'code' => 422];
            }

            $payment = $this->payments->create($actor, $order, [
                'method' => 'cash',
                'amount' => $outstanding,
                'source' => 'on_site',
                'note' => 'on_site',
            ]);

            if (! $payment['ok']) {
                return $payment;
            }

            $attendance = $this->recordAttendance($actor, $lockedTicket, $order->fresh(), $idTanggal, $gate);

            return [
                'ok' => true,
                'message' => 'Pembayaran dan kehadiran berhasil disimpan',
                'code' => 200,
                'data' => $this->scannerPayload($lockedTicket->fresh(), $order->fresh()),
                'attendance' => $attendance,
            ];
        });
    }

    public function verdict(Ticket $ticket): string
    {
        return match ($ticket->status) {
            TicketStatus::ISSUED->value => 'ok',
            TicketStatus::CHECKED_IN->value => 'duplicate',
            default => 'rejected',
        };
    }

    protected function validate(
        User $actor,
        Ticket $ticket,
        int $idTanggal,
        ?int $idEvent = null,
        bool $authorize = true
    ): array {
        if ($authorize && ! $this->canCheckIn($actor)) {
            return ['ok' => false, 'message' => 'Forbidden', 'code' => 403];
        }

        if ($ticket->expired_at && $ticket->expired_at < now()) {
            return ['ok' => false, 'message' => 'Tiket sudah expired', 'code' => 409];
        }

        $order = $ticket->order;
        if (! $order) {
            return ['ok' => false, 'message' => 'Data pesanan tidak ditemukan', 'code' => 422];
        }

        if ($idEvent !== null && (int) $order->id_event !== $idEvent) {
            return ['ok' => false, 'message' => 'Tiket tidak berlaku untuk event ini', 'code' => 422];
        }

        $eventValidation = $this->validateEvent($idEvent ?? (int) $order->id_event);
        if (! $eventValidation['ok']) {
            return $eventValidation;
        }

        $orderValidation = $this->validateOrder($order);
        if (! $orderValidation['ok']) {
            return $orderValidation;
        }

        $day = Tanggal_event::where('id', $idTanggal)
            ->where('id_event', $order->id_event)
            ->first();
        if (! $day) {
            return ['ok' => false, 'message' => 'Tanggal kegiatan tidak valid untuk tiket ini', 'code' => 422];
        }

        return ['ok' => true, 'order' => $order, 'day' => $day];
    }

    protected function canCheckIn(User $actor): bool
    {
        return Gate::forUser($actor)->allows('checkIn', new Ticket);
    }

    protected function validateEvent(int $idEvent): array
    {
        $event = Event::find($idEvent);
        if (! $event) {
            return ['ok' => false, 'message' => 'Event tidak ditemukan', 'code' => 422];
        }

        if ((string) $event->is_active !== '1') {
            return ['ok' => false, 'message' => 'Event tidak aktif', 'code' => 409];
        }

        return ['ok' => true, 'event' => $event];
    }

    protected function validateOrder(Order $order): array
    {
        if (! in_array($order->status_registrasi, [
            OrderStatus::REGISTERED->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::CHECKED_IN->value,
        ], true)) {
            return ['ok' => false, 'message' => 'Status registrasi tidak dapat digunakan untuk check-in', 'code' => 409];
        }

        return ['ok' => true];
    }

    protected function resolveTicket(string $identifier): array
    {
        $tickets = Ticket::query()
            ->where('uuid', $identifier)
            ->orWhere('nomor_ticket', $identifier)
            ->orWhere('qr_payload', $identifier)
            ->limit(2)
            ->get();

        if ($tickets->isEmpty()) {
            return ['ok' => false, 'message' => 'Tiket tidak ditemukan', 'code' => 404];
        }

        if ($tickets->count() !== 1) {
            return ['ok' => false, 'message' => 'Identifier tiket tidak unik', 'code' => 409];
        }

        return ['ok' => true, 'ticket' => $tickets->first()];
    }

    protected function resolveMemberCard(string $identifier, int $idEvent): array
    {
        $members = User::where('id_anggota', $identifier)
            ->get()
            ->filter(fn (User $user) => (string) $user->id_anggota === $identifier)
            ->values();

        if ($members->isEmpty()) {
            return ['ok' => false, 'message' => 'Anggota tidak ditemukan', 'code' => 404];
        }

        if ($members->count() !== 1) {
            return ['ok' => false, 'message' => 'Kartu anggota tidak unik', 'code' => 409];
        }

        $orders = Order::where('id_event', $idEvent)
            ->where('id_anggota', $members->first()->id_anggota)
            ->limit(2)
            ->get();

        if ($orders->isEmpty()) {
            return ['ok' => false, 'message' => 'Anggota belum terdaftar pada event ini', 'code' => 404];
        }

        if ($orders->count() !== 1) {
            return ['ok' => false, 'message' => 'Registrasi anggota tidak unik', 'code' => 409];
        }

        $order = $orders->first();
        $orderValidation = $this->validateOrder($order);
        if (! $orderValidation['ok']) {
            return $orderValidation;
        }

        $tickets = $order->tickets()
            ->whereIn('status', [TicketStatus::ISSUED->value, TicketStatus::CHECKED_IN->value])
            ->limit(2)
            ->get();

        if ($tickets->isEmpty()) {
            return ['ok' => false, 'message' => 'Tiket aktif tidak ditemukan untuk registrasi ini', 'code' => 404];
        }

        if ($tickets->count() !== 1) {
            return ['ok' => false, 'message' => 'Tiket aktif untuk registrasi ini tidak unik', 'code' => 409];
        }

        return ['ok' => true, 'ticket' => $tickets->first()];
    }

    protected function recordAttendance(User $actor, Ticket $ticket, Order $order, int $idTanggal, ?string $gate): Prisensi_kehadiran
    {
        $scannedAt = now();

        $ticket->forceFill([
            'status' => TicketStatus::CHECKED_IN->value,
            'used_at' => $scannedAt,
            'updated_by' => $actor->id,
        ])->save();

        $attendance = Prisensi_kehadiran::create([
            'id_event' => $order->id_event,
            'id_tanggal' => $idTanggal,
            'id_anggota' => $order->id_anggota,
            'tanggal_kehadiran' => $scannedAt,
            'jam_kehadiran' => $scannedAt->format('Y-m-d H:i:s'),
            'id_ticket' => $ticket->id,
            'gate' => $gate,
            'scanned_at' => $scannedAt,
            'scanned_by' => $actor->id,
        ]);

        $ticket->logs()->create([
            'id_ticket' => $ticket->id,
            'old_status' => TicketStatus::ISSUED->value,
            'new_status' => TicketStatus::CHECKED_IN->value,
            'note' => 'check_in',
            'changed_by' => $actor->id,
        ]);

        DB::afterCommit(fn () => event(new TicketStatusChanged(
            $ticket,
            TicketStatus::ISSUED->value,
            TicketStatus::CHECKED_IN->value,
            'check_in',
            $actor,
            $gate,
        )));

        return $attendance;
    }

    protected function checkInPayload(Ticket $ticket, Order $order, Prisensi_kehadiran $attendance): array
    {
        $participant = User::where('id_anggota', $order->id_anggota)->first();

        return [
            'ticket' => $ticket,
            'attendance' => $attendance,
            'participant' => $participant ? [
                'id' => $participant->id,
                'id_anggota' => $participant->id_anggota,
                'name' => $participant->name,
            ] : null,
            'event' => [
                'id_event' => $order->id_event,
                'event_name' => $order->event_name,
            ],
        ];
    }

    protected function scannerPayload(Ticket $ticket, Order $order): array
    {
        $participant = User::where('id_anggota', $order->id_anggota)->first();
        $profile = $participant ? DataUser::where('id_users', $participant->id)->first() : null;
        $attendance = Prisensi_kehadiran::where('id_ticket', $ticket->id)->orderBy('id')->first();
        $paidPayment = $order->payments()
            ->where('status', PaymentStatus::PAID->value)
            ->latest('id')
            ->first();
        $outstanding = $this->payments->outstanding($order);
        $paid = $this->isPaid($order, $outstanding);
        $paymentAmount = $paid && $outstanding['paid'] <= config('payment.amount_epsilon', 0.001)
            ? (float) $order->total_amount
            : ($paid ? $outstanding['paid'] : $outstanding['outstanding']);

        return [
            'ticket' => [
                'id' => $ticket->id,
                'uuid' => $ticket->uuid,
                'nomor_ticket' => $ticket->nomor_ticket,
                'status' => $ticket->status,
            ],
            'participant' => [
                'id' => $participant?->id,
                'id_anggota' => $order->id_anggota,
                'name' => $participant?->name ?? 'Peserta',
                'foto' => $profile?->foto,
                'niqobah' => $profile?->niqobah,
            ],
            'event' => [
                'id_event' => $order->id_event,
                'event_name' => $order->event_name,
            ],
            'payment' => [
                'choice' => $order->payment_choice ?: 'pay_now',
                'status' => $paid ? PaymentStatus::PAID->value : PaymentStatus::PENDING->value,
                'amount' => $paymentAmount,
                'source' => $paidPayment?->source ?: $paidPayment?->method,
                'paid_at' => $paidPayment?->paid_at?->toIso8601String(),
            ],
            'attendance' => [
                'status' => $attendance ? 'present' : 'not_present',
                'scanned_at' => $attendance?->scanned_at?->toIso8601String(),
                'scanned_by' => $attendance?->scanned_by,
                'gate' => $attendance?->gate,
            ],
        ];
    }

    protected function duplicate(Ticket $ticket): array
    {
        $first = Prisensi_kehadiran::where('id_ticket', $ticket->id)
            ->orderBy('id')
            ->first();

        return [
            'ok' => false,
            'message' => 'Tiket sudah digunakan',
            'code' => 409,
            'data' => [
                'first_scanned_at' => $first?->scanned_at,
                'first_scanned_by' => $first?->scanned_by,
            ],
        ];
    }

    protected function isPaid(Order $order, array $summary): bool
    {
        return $order->payment_status === PaymentStatus::PAID->value
            || $summary['outstanding'] <= config('payment.amount_epsilon', 0.001);
    }

    protected function amountInCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
