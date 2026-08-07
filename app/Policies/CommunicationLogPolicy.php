<?php

namespace App\Policies;

use App\Models\CommunicationLog;
use App\Models\User;
use App\Support\RoleGuard;

/**
 * Authorization for the Communication Log audit trail (rule 4).
 *
 * Communication logs are a system audit record — only admin/staff may read
 * them. (Notification Center access is owner-scoped and handled separately by
 * NotificationService.) The log is immutable: no create/update/delete abilities
 * are exposed at all.
 */
class CommunicationLogPolicy
{
    /**
     * Staff (Finance / Ketua / Admin) may view the audit log.
     */
    public function view(User $user, CommunicationLog $log): bool
    {
        return RoleGuard::canVerify($user);
    }
}