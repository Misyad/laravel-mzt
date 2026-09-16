<?php

namespace App\Support;

use App\Enums\KtaPrintStatus;

/**
 * KTA print request transition rules (PRD v3.0 §16/§20).
 *
 * ```
 * menunggu_pembayaran --paymenku paid--> menunggu_cetak
 * menunggu_cetak      --admin-----------> sudah_dicetak
 * sudah_dicetak + pickup   --admin------> siap_diambil
 * sudah_dicetak + delivery --admin------> dikirim
 * siap_diambil | dikirim   --admin------> selesai
 *
 * menunggu_pembayaran -> ditolak            (admin reject)
 * menunggu_pembayaran -> pembayaran_expired (payment failed/expired)
 * ```
 *
 * There is intentionally no `menunggu_verifikasi`: payment is confirmed by the
 * verified Paymenku webhook, never manually.
 */
class KtaPrintStateMachine
{
    /**
     * Allowed admin transitions, keyed by current status.
     *
     * @return array<string, list<string>>
     */
    public static function allowedTransitions(): array
    {
        return [
            KtaPrintStatus::MENUNGGU_PEMBAYARAN->value => [
                KtaPrintStatus::MENUNGGU_CETAK->value, // via payment only, guarded in service
                KtaPrintStatus::DITOLAK->value,
                KtaPrintStatus::PEMBAYARAN_EXPIRED->value,
            ],
            KtaPrintStatus::MENUNGGU_CETAK->value => [
                KtaPrintStatus::SUDAH_DICETAK->value,
                KtaPrintStatus::DITOLAK->value,
            ],
            KtaPrintStatus::SUDAH_DICETAK->value => [
                KtaPrintStatus::SIAP_DIAMBIL->value, // pickup
                KtaPrintStatus::DIKIRIM->value,      // delivery
            ],
            KtaPrintStatus::SIAP_DIAMBIL->value => [
                KtaPrintStatus::SELESAI->value,
            ],
            KtaPrintStatus::DIKIRIM->value => [
                KtaPrintStatus::SELESAI->value,
            ],
            KtaPrintStatus::SELESAI->value => [],
            KtaPrintStatus::DITOLAK->value => [],
            KtaPrintStatus::PEMBAYARAN_EXPIRED->value => [],
        ];
    }

    /**
     * Whether an admin may move from `$from` to `$to`.
     */
    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedTransitions()[$from] ?? [], true);
    }

    /**
     * Transitions an admin may NOT force. Payment-driven transitions
     * (`menunggu_pembayaran → menunggu_cetak`) and delivery-method branching
     * are enforced by the service, so the plain state machine allows them but
     * the admin surface does not expose them.
     *
     * @return string[]
     */
    public static function adminForbiddenTargets(): array
    {
        return [
            KtaPrintStatus::MENUNGGU_CETAK->value, // only via verified payment
            KtaPrintStatus::MENUNGGU_PEMBAYARAN->value,
        ];
    }
}
