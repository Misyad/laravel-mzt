<?php

namespace App\Support;

use App\Models\HakAksesRole;
use App\Models\User;

/**
 * Centralises RBAC checks for the payment engine (Phase 2B).
 *
 * Roles are stored in `hak_akses_role` (PRD §21.4: Alumni / Dashboard / Event /
 * Finance / Ketua / Administrator). Back-office permissions are granted to the
 * staff set (dashboard/event/finance/ketua/admin); payment verification is
 * restricted to finance/ketua/admin per PRD §17.12.
 */
class RoleGuard
{
    /** Back-office roles that may create / view all resources. */
    public const STAFF_ROLES = ['dashboard', 'event', 'finance', 'ketua', 'admin'];

    /** Roles allowed to approve/reject payments (PRD §17.12: Finance & Admin). */
    public const VERIFIER_ROLES = ['finance', 'ketua', 'admin'];

    /**
     * The role names attached to a user.
     *
     * @return string[]
     */
    public static function roles(User $user): array
    {
        return HakAksesRole::where('id_users', $user->id)
            ->pluck('nama_role')
            ->map(fn ($r) => strtolower((string) $r))
            ->toArray();
    }

    /**
     * Whether the user holds at least one of the given roles.
     *
     * @param  string[]  $allowed
     */
    public static function hasAnyRole(User $user, array $allowed): bool
    {
        return count(array_intersect(self::roles($user), array_map('strtolower', $allowed))) > 0;
    }

    /** Whether the user is a back-office staff member. */
    public static function isStaff(User $user): bool
    {
        return self::hasAnyRole($user, self::STAFF_ROLES);
    }

    /** Whether the user may verify (approve/reject) payments. */
    public static function canVerify(User $user): bool
    {
        return self::hasAnyRole($user, self::VERIFIER_ROLES);
    }
}