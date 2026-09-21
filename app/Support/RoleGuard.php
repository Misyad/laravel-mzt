<?php

namespace App\Support;

use App\Models\HakAksesRole;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleGuard
{
    public const REQUIRED_MEMBER_ROLES = ['anggota', 'profil'];

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

    public static function validateMemberRoleSelection(array $roles): array
    {
        $normalized = [];

        foreach ($roles as $role) {
            if (! is_string($role) || strlen($role) > 255) {
                throw ValidationException::withMessages([
                    'roles' => ['Role harus berupa teks yang valid.'],
                ]);
            }

            $name = self::normalize($role);
            if ($name === '' || isset($normalized[$name])) {
                throw ValidationException::withMessages([
                    'roles' => ['Role tidak boleh kosong atau duplikat.'],
                ]);
            }

            $normalized[$name] = true;
        }

        $catalogState = [];
        foreach (RoleUser::query()->get(['nama_role', 'is_active']) as $catalogRole) {
            $name = self::normalize($catalogRole->nama_role);
            if ($name === '' || array_key_exists($name, $catalogState)) {
                throw ValidationException::withMessages([
                    'roles' => ['Katalog role tidak valid.'],
                ]);
            }

            $catalogState[$name] = (string) $catalogRole->is_active === '1';
        }

        if ($catalogState === []) {
            throw ValidationException::withMessages([
                'roles' => ['Katalog role tidak tersedia.'],
            ]);
        }

        foreach (self::REQUIRED_MEMBER_ROLES as $role) {
            if (array_key_exists($role, $catalogState) && $catalogState[$role] !== true) {
                throw ValidationException::withMessages([
                    'roles' => ['Role wajib anggota tidak aktif.'],
                ]);
            }
        }

        foreach (array_keys($normalized) as $role) {
            if (in_array($role, self::REQUIRED_MEMBER_ROLES, true) && ! array_key_exists($role, $catalogState)) {
                continue;
            }

            if (($catalogState[$role] ?? false) !== true) {
                throw ValidationException::withMessages([
                    'roles' => ['Role yang dipilih tidak tersedia.'],
                ]);
            }
        }

        return array_values(array_unique(array_merge(
            self::REQUIRED_MEMBER_ROLES,
            array_keys($normalized)
        )));
    }

    public static function replaceMemberRoles(User $user, array $roles): void
    {
        $roles = self::validateMemberRoleSelection($roles);

        DB::transaction(function () use ($user, $roles): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            HakAksesRole::where('id_users', $user->id)->delete();

            HakAksesRole::insert(array_map(fn (string $role) => [
                'id_users' => $user->id,
                'nama_role' => $role,
                'hak_akses' => 'access',
            ], $roles));
        });
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
            fn (string $role) => ($catalogState[$role] ?? in_array($role, self::REQUIRED_MEMBER_ROLES, true)) === true
        ));
    }
}
