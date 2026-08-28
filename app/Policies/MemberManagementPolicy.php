<?php

namespace App\Policies;

use App\Support\MemberManagement;
use App\Models\User;
use App\Support\RoleGuard;

/**
 * Member administration authorization (C-01 gate).
 *
 * Boundaries:
 *  - viewDirectory  : staff set (dashboard/event/finance/ketua/admin) — member
 *                     PII directory reads.
 *  - manageAccounts : ketua/admin ONLY — account creation, password reset,
 *                     activation status, bulk generation.
 *  - writeMember    : ketua/admin ONLY — member create/update/delete.
 *
 * CRITICAL: `writeMember` guards POST /members, which historically accepted a
 * request-supplied `roles[]` payload. With this gate, only ketua/admin can
 * reach that code path at all, so no caller can elevate beyond its own
 * capability (privilege-escalation closure).
 */
class MemberManagementPolicy
{
    public function viewDirectory(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    public function manageAccounts(User $user): bool
    {
        return RoleGuard::isAdmin($user);
    }

    public function writeMember(User $user): bool
    {
        return RoleGuard::isAdmin($user);
    }
}
