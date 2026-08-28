<?php

namespace App\Support;

/**
 * Capability marker for content & event management (C-01 authorization gate).
 *
 * Interim boundary per the approved C-01 decision: global back-office staff
 * roles (dashboard/event/finance/ketua/admin). Event-ASSIGNED operator scope
 * is explicitly deferred — it requires a separate schema/design decision and
 * MUST NOT be inferred from this policy.
 */
class Content
{
}
