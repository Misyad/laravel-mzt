<?php

namespace App\Support;

/**
 * Capability marker for member administration (C-01 authorization gate).
 *
 * Pure marker used as the policy subject so member-management abilities can be
 * granted module-level via MemberManagementPolicy:
 *   - viewDirectory : staff set (dashboard/event/finance/ketua/admin)
 *   - manageAccounts: ketua/admin only (account lifecycle)
 *   - writeMember   : ketua/admin only (member create/update/delete)
 */
class MemberManagement
{
}
