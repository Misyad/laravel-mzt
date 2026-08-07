<?php

namespace App\Support;

/**
 * Capability marker representing the Dashboard read model (Sprint 5A).
 *
 * The Dashboard is a pure read model, not an Eloquent entity. This class exists
 * purely as the policy subject so module-level dashboard abilities
 * (DashboardPolicy::viewOverview / viewRevenue / viewPayment) can be granted via
 * `Gate::allows('...', Dashboard::class)`.
 */
class Dashboard
{
}