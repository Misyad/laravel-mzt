<?php

namespace App\Support;

/**
 * Capability marker representing the Phase 2D event-day operations read model.
 *
 * Mirrors App\Support\Dashboard: a pure marker used as the policy subject so
 * module-level Phase 2D abilities (OperationalPolicy) can be granted via
 * `Gate::allows('...', Operational::class)`.
 */
class Operational
{
}