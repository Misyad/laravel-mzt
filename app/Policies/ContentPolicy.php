<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Content;
use App\Support\RoleGuard;

/**
 * Content & event management authorization (C-01 gate).
 *
 * Interim global-role boundary (approved): any back-office staff role may
 * manage events and site content (news, carousel, org info). Event-ASSIGNED
 * operator granularity is deferred to a separate schema/design decision and
 * is intentionally NOT represented here.
 *
 * Sanitization (R1/R2 HtmlSanitizer) remains a separate, independent concern —
 * this policy only decides WHO may attempt the write.
 */
class ContentPolicy
{
    /** Create / update / delete events. */
    public function manageEvents(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }

    /** Publish/update/remove news, carousel slides, organization info. */
    public function manageContent(User $user): bool
    {
        return RoleGuard::isStaff($user);
    }
}
