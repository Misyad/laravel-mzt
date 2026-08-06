<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\RoleGuard;

/**
 * Payment authorization (Phase 2B).
 *
 * Matching PRD §17.12 matrix (§21.4 roles):
 *  - Upload Payment  : Owner / Panitia / Finance / Admin
 *  - Payment Detail  : Owner / staff
 *  - Payment Verif   : Finance / Admin only
 *  - Create Payment  : staff (panitia/operator, plan Lampiran #4)
 */
class PaymentPolicy
{
    /**
     * Whether the user may upload a proof against an order (owner or staff).
     */
    public function upload(User $user, Order $order): bool
    {
        return $user->id_anggota === $order->id_anggota || RoleGuard::isStaff($user);
    }

    /**
     * Whether the user may view a payment (owner or staff).
     */
    public function view(User $user, Payment $payment): bool
    {
        $order = $payment->order;
        if (!$order) {
            return RoleGuard::isStaff($user);
        }

        return $user->id_anggota === $order->id_anggota || RoleGuard::isStaff($user);
    }

    /**
     * Whether the user may view an order's payment history.
     */
    public function viewOrder(User $user, Order $order): bool
    {
        return $user->id_anggota === $order->id_anggota || RoleGuard::isStaff($user);
    }

    /**
     * Whether the user may create a payment for an order (staff only).
     */
    public function create(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    /**
     * Whether the user may verify (approve/reject) a payment (Finance/Admin).
     */
    public function verify(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }
}