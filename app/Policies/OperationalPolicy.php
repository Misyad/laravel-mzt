<?php

namespace App\Policies;

use App\Models\User;
use App\Support\RoleGuard;

/**
 * Phase 2D event-day operations authorization (read-only).
 *
 * Matches the authorization matrix in PHASE2D_IMPLEMENTATION_PLAN.md:
 *  - viewOperational      : any back-office staff (dashboard/event/finance/ketua/admin)
 *  - viewParticipantPII   : verifier only (finance/ketua/admin)
 *  - viewFinancialQueue   : verifier only (finance/ketua/admin)
 *
 * The policy subject is App\Support\Operational (a capability marker, not an
 * Eloquent model), so reads are granted at the module level.
 */
class OperationalPolicy
{
    /**
     * Aggregate event-day operations (overview, attendance, gate monitoring).
     */
    public function viewOperational(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    /**
     * Fine-grained audit log data (verifier/authorized audit reader only).
     */
    public function viewAuditLog(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }

    /**
     * Fine-grained participant data (names, member ids, contact info).
     */
    public function viewParticipantPII(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }

    /**
     * Financial queue / financial drill-down data.
     */
    public function viewFinancialQueue(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }
}