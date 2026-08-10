<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleGuard;

/**
 * Dashboard authorization (Sprint 5A / 5B.1).
 *
 * Matches the finance Role Matrix in SPRINT5B_PLANNING.md §13:
 *  - viewOverview  : any back-office staff (dashboard/event/finance/ketua/admin)
 *  - viewRevenue   : finance/ketua/admin (canVerify set)
 *  - viewPayment   : finance/ketua/admin
 *  - viewTickets   : any back-office staff (Ticket Monitoring)
 *  - viewOperational : any back-office staff (Operational Summary)
 *
 * The policy subject is the Dashboard read model (a capability marker, not an
 * Eloquent model) so that dashboard reads are granted at the module level.
 */
class DashboardPolicy
{
    public function viewOverview(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    public function viewRevenue(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }

    public function viewPayment(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }

    public function viewTickets(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    public function viewOperational(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    /**
     * Legacy attendance list (ApiController::attendanceIndex) — any back-office
     * staff may read the (PII-bearing) attendance list. Alumni / other roles 403.
     */
    public function viewAttendance(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    /**
     * Legacy financial transactions (ApiController::transactionsIndex) —
     * verifier-only (finance/ketua/admin). Closed to other roles.
     */
    public function viewTransactions(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }
}