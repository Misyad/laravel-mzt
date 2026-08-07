<?php

namespace App\Listeners\Communication;

use App\Enums\CommunicationChannel;
use App\Events\TicketStatusChanged;
use App\Models\User;
use App\Services\CommunicationDispatcher;

/**
 * Forwards TicketStatusChanged to the Communication Engine (forward-only).
 *
 * Only the "revoke" transition maps to a template in Sprint 4; other lifecycle
 * changes (reissue / future check-in) are ignored until a template exists.
 * No business logic here.
 */
class TicketStatusChangedListener
{
    public function __construct(
        protected CommunicationDispatcher $dispatcher,
    ) {
    }

    public function handle(TicketStatusChanged $event): void
    {
        if ($event->action !== 'revoke') {
            return;
        }

        $order = $event->ticket->order;

        if (!$order) {
            return;
        }

        $recipient = User::where('id_anggota', $order->id_anggota)->first();

        $this->dispatcher->dispatch(
            'TicketStatusChanged',
            $recipient?->id,
            CommunicationChannel::IN_APP,
            'ticket-revoked',
            [
                'nomor_ticket' => $event->ticket->nomor_ticket,
                'event' => $order->event_name,
            ],
        );
    }
}