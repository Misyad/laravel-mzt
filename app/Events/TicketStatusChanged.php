<?php

namespace App\Events;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a Ticket's lifecycle changes (reissue / revoke, and later
 * check-in) (PRD §16.x / ADR-016).
 *
 * TicketIssued is the specific "just issued" signal; TicketStatusChanged is the
 * generic subscription point so the Communication Engine (Sprint 4) does not
 * need to know whether the change came from a reissue, a revoke, or a check-in.
 */
class TicketStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  string  $oldStatus  previous status value
     * @param  string  $newStatus  new status value
     * @param  string  $action     reason for the change (reissue|revoke|...)
     * @param  \App\Models\User|null  $actor  user who caused the change
     */
    public function __construct(
        public Ticket $ticket,
        public string $oldStatus,
        public string $newStatus,
        public string $action,
        public ?User $actor = null,
        public ?string $note = null,
    ) {
    }
}