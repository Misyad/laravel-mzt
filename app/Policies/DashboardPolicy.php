<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleGuard;

/**
 * Dashboard authorization (Sprint 5A).
 *
 * Matches the finance Role Matrix in SPRINT5_PLANNING.md / Phase 2B:
 *  - viewOverview : any back-office staff (dashboard/event/finance/ketua/admin)
 *  - viewRevenue   : finance/ketua/admin (canVerify set)
 *  - viewPayment   : finance/ketua/admin
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
}