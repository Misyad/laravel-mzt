<?php

namespace App\Services;

use App\Models\DataUser;
use App\Models\HakAksesRole;
use App\Models\User;
use App\Support\RoleGuard;

class PublicMemberEligibilityService
{
    public function eligible(User $user): bool
    {
        if ($user->account_claimed_at !== null || (bool) $user->account_setup_required || trim((string) $user->id_anggota) === '') {
            return false;
        }

        if (DataUser::where('id_users', $user->id)->count() !== 1) {
            return false;
        }

        $roles = HakAksesRole::where('id_users', $user->id)->get(['nama_role', 'hak_akses']);
        $state = RoleGuard::roleState($roles);

        if ($state['invalid_access'] !== [] || $state['conflicts'] !== [] || $state['denied'] !== []) {
            return false;
        }

        if (array_diff($state['raw'], ['anggota', 'profil']) !== []) {
            return false;
        }

        return in_array('anggota', $state['effective'], true);
    }
}
