<?php

namespace App\Policies;

use App\Models\KtaPrintRequest;
use App\Models\User;
use App\Support\RoleGuard;

/**
 * Physical KTA print request authorization (PRD v3.0).
 *
 *  - view             : verifier (finance/ketua/admin)
 *  - viewQueue        : staff (dashboard/event/finance/ketua/admin)
 *  - process          : verifier (finance/ketua/admin), matching the payment
 *                       verifier set used elsewhere in the dashboard.
 */
class KtaPrintRequestPolicy
{
    public function view(User $user, KtaPrintRequest $request): bool
    {
        return RoleGuard::canVerify($user);
    }

    public function viewQueue(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    public function process(User $user): bool
    {
        return RoleGuard::canVerify($user);
    }
}
