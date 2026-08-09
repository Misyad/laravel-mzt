<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Support\RoleGuard;

/**
 * Ticket authorization (Phase 2B).
 *
 * Matching PRD §17.12 matrix (§21.4 roles):
 *  - View / Download : Owner / Staff
 *  - Reissue/Revoke   : Staff (Finance / Ketua / Admin per PRD §17.12)
 */
class TicketPolicy
{
    /**
     * Whether the user may view a ticket (owner or staff).
     */
    public function view(User $user, Ticket $ticket): bool
    {
        $order = $ticket->order;
        if (!$order) {
            return RoleGuard::isStaff($user);
        }

        return $user->id_anggota === $order->id_anggota || RoleGuard::isStaff($user);
    }

    /**
     * Whether the user may download a ticket PDF (same rights as view).
     */
    public function download(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /**
     * Whether the user may re-issue a ticket (staff / Finance-Ketua-Admin).
     */
    public function reissue(User $user, Ticket $ticket): bool
    {
        return RoleGuard::canVerify($user);
    }

    /**
     * Whether the user may revoke a ticket (staff / Finance-Ketua-Admin).
     */
    public function revoke(User $user, Ticket $ticket): bool
    {
        return RoleGuard::canVerify($user);
    }

    /**
     * Whether the user may check in a ticket (Phase 2C — PRD §17.12):
     * prisensi / event operators, or any verifier (finance / ketua / admin).
     * Permission is role-based, so the ticket instance is not consulted.
     */
    public function checkIn(User $user, Ticket $ticket): bool
    {
        return RoleGuard::canCheckIn($user);
    }
}