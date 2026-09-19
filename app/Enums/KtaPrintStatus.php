<?php

namespace App\Enums;

/**
 * Physical KTA print request status (PRD v3.0 §16).
 *
 * Lifecycle:
 *   menunggu_pembayaran → menunggu_cetak → sudah_dicetak
 *     → (pickup) siap_diambil → selesai
 *     → (delivery) dikirim → selesai
 *
 * Terminal: selesai, ditolak, pembayaran_expired.
 * There is deliberately NO `menunggu_verifikasi` — payment is confirmed
 * automatically by the Paymenku webhook, never by a human.
 */
enum KtaPrintStatus: string
{
    case MENUNGGU_PEMBAYARAN = 'menunggu_pembayaran';
    case MENUNGGU_CETAK = 'menunggu_cetak';
    case SUDAH_DICETAK = 'sudah_dicetak';
    case SIAP_DIAMBIL = 'siap_diambil';
    case DIKIRIM = 'dikirim';
    case SELESAI = 'selesai';
    case DITOLAK = 'ditolak';
    case PEMBAYARAN_EXPIRED = 'pembayaran_expired';

    /**
     * All values as a list (useful for validation rules).
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Statuses that count as an "active" request (PRD v3.0 §18). A member may
     * hold at most one active request at a time.
     *
     * @return string[]
     */
    public static function activeValues(): array
    {
        return [
            self::MENUNGGU_PEMBAYARAN->value,
            self::MENUNGGU_CETAK->value,
            self::SUDAH_DICETAK->value,
            self::SIAP_DIAMBIL->value,
            self::DIKIRIM->value,
        ];
    }

    /**
     * Statuses shown in the production (print) queue. `menunggu_pembayaran`
     * is intentionally excluded (PRD v3.0 §19).
     *
     * @return string[]
     */
    public static function productionQueueValues(): array
    {
        return [
            self::MENUNGGU_CETAK->value,
            self::SUDAH_DICETAK->value,
            self::SIAP_DIAMBIL->value,
            self::DIKIRIM->value,
        ];
    }

    public static function printableValues(): array
    {
        return [...self::productionQueueValues(), self::SELESAI->value];
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [
            self::SELESAI->value,
            self::DITOLAK->value,
            self::PEMBAYARAN_EXPIRED->value,
        ], true);
    }
}
