<?php

namespace App\Support;

use App\Models\HakAksesRole;
use App\Models\RoleUser;
use App\Models\User;

class RoleGuard
{
    public const STAFF_ROLES = ['dashboard', 'event', 'finance', 'ketua', 'admin'];

    public const VERIFIER_ROLES = ['finance', 'ketua', 'admin'];

    public const CHECK_IN_ROLES = ['prisensi', 'event'];

    public const ADMIN_ROLES = ['ketua', 'admin'];

    public const KTA_CARD_ROLES = ['id_card', 'ketua', 'admin'];

    public static function normalize($value): string
    {
        return strtolower(trim((string) $value));
    }

    public static function roleState(iterable $rows, bool $filterCatalog = true): array
    {
        $rawRoles = [];
        $invalidAccess = [];
        $granted = [];
        $denied = [];

        foreach ($rows as $row) {
            $role = self::normalize($row->nama_role ?? '');
            $access = self::normalize($row->hak_akses ?? '');
            $rawRoles[] = $role;

            if ($access === 'access') {
                $granted[$role] = true;
            } elseif ($access === 'no_accesss') {
                $denied[$role] = true;
            } else {
                $invalidAccess[] = $access;
            }
        }

        $effective = [];
        foreach (array_keys($granted) as $role) {
            if ($role !== '' && ! isset($denied[$role])) {
                $effective[] = $role;
            }
        }

        if ($filterCatalog) {
            $effective = self::filterByActiveCatalog($effective);
        }
        sort($effective);

        $conflicts = array_values(array_intersect(array_keys($granted), array_keys($denied)));
        sort($conflicts);

        $deniedRoles = array_keys($denied);
        sort($deniedRoles);

        return [
            'effective' => array_values(array_unique($effective)),
            'raw' => array_values(array_unique($rawRoles)),
            'denied' => $deniedRoles,
            'conflicts' => $conflicts,
            'invalid_access' => array_values(array_unique($invalidAccess)),
        ];
    }

    public static function roles(User $user): array
    {
        try {
            $rows = HakAksesRole::where('id_users', $user->id)->get(['nama_role', 'hak_akses']);
        } catch (\Throwable $exception) {
            return [];
        }

        return self::roleState($rows)['effective'];
    }

    public static function hasAnyRole(User $user, array $allowed): bool
    {
        $allowed = array_map([self::class, 'normalize'], $allowed);

        return count(array_intersect(self::roles($user), $allowed)) > 0;
    }

    public static function isStaff(User $user): bool
    {
        return self::hasAnyRole($user, self::STAFF_ROLES);
    }

    public static function isAdmin(User $user): bool
    {
        return self::hasAnyRole($user, self::ADMIN_ROLES);
    }

    public static function canViewKtaCards(User $user): bool
    {
        return self::hasAnyRole($user, self::KTA_CARD_ROLES);
    }

    public static function canVerify(User $user): bool
    {
        return self::hasAnyRole($user, self::VERIFIER_ROLES);
    }

    public static function canCheckIn(User $user): bool
    {
        return self::hasAnyRole($user, self::CHECK_IN_ROLES) || self::canVerify($user);
    }

    private static function filterByActiveCatalog(array $roles): array
    {
        try {
            $catalog = RoleUser::query()->get(['nama_role', 'is_active']);
        } catch (\Throwable $exception) {
            return [];
        }

        if ($catalog->isEmpty()) {
            return [];
        }

        $catalogState = [];
        foreach ($catalog as $catalogRole) {
            $name = self::normalize($catalogRole->nama_role);
            if ($name === '') {
                continue;
            }

            if (array_key_exists($name, $catalogState)) {
                return [];
            }

            $catalogState[$name] = (string) $catalogRole->is_active === '1';
        }

        return array_values(array_filter(
            $roles,
            fn (string $role) => ($catalogState[$role] ?? false) === true
        ));
    }
}
