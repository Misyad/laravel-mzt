<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched the first time a Ticket is issued (PRD §16.x / ADR-016).
 *
 * This is a specific signal for "a ticket is now usable". Generic lifecycle
 * changes (reissue, revoke, and later check-in) are covered by
 * TicketStatusChanged so the Communication Engine (Sprint 4) can subscribe to a
 * single event.
 */
class TicketIssued
{
    use Dispatchable, SerializesModels;

    /**
     * @param  \App\Models\User|null  $actor  user who caused the issuance
     */
    public function __construct(
        public Ticket $ticket,
        public ?User $actor = null,
    ) {
    }
}