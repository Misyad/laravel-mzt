<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * Enforces event capacity rules before a registration is created (Phase 2A):
 *  - visibility  : public (anyone) / internal (logged-in alumni) / private (blocked in 2A)
 *  - kuota       : maximum accepted orders
 *  - window      : registrasi_dibuka / registrasi_ditutup
 *
 * Returns a success flag + message so the controller can map it to a
 * stable HTTP response.
 */
class EventCapacityService
{
    /**
     * Validate that a registration is currently allowed for the event.
     *
     * @param  \App\Models\Event  $event
     * @return array{ok: bool, message: string|null, code: int}
     */
    public function assertRegistrable(Event $event): array
    {
        if (!$event->is_active) {
            return ['ok' => false, 'message' => 'Event tidak aktif', 'code' => 403];
        }

        if ($event->visibility === 'private') {
            return ['ok' => false, 'message' => 'Pendaftaran event ini belum dibuka untuk umum', 'code' => 403];
        }

        if ($event->visibility === 'internal' && !auth()->check()) {
            return ['ok' => false, 'message' => 'Silakan masuk terlebih dahulu untuk mendaftar', 'code' => 401];
        }

        $now = Carbon::now();

        if ($event->registrasi_dibuka && $now->lt(Carbon::parse($event->registrasi_dibuka))) {
            return ['ok' => false, 'message' => 'Pendaftaran belum dibuka', 'code' => 403];
        }

        if ($event->registrasi_ditutup && $now->gt(Carbon::parse($event->registrasi_ditutup))) {
            return ['ok' => false, 'message' => 'Pendaftaran sudah ditutup', 'code' => 403];
        }

        if (!empty($event->kuota)) {
            $count = Order::where('id_event', $event->id)
                ->where('status_registrasi', '<>', 'cancelled')
                ->count();
            if ($count >= (int) $event->kuota) {
                return ['ok' => false, 'message' => 'Kuota event sudah penuh', 'code' => 409];
            }
        }

        return ['ok' => true, 'message' => null, 'code' => 200];
    }
}
