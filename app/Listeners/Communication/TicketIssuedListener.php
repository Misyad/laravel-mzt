<?php

namespace App\Listeners\Communication;

use App\Enums\CommunicationChannel;
use App\Events\TicketIssued;
use App\Models\User;
use App\Services\CommunicationDispatcher;

/**
 * Forwards TicketIssued to the Communication Engine (forward-only).
 *
 * Only maps the event to the ticket-issued template, resolves the recipient and
 * hands the payload over. Issuance rules stay in TicketService.
 */
class TicketIssuedListener
{
    public function __construct(
        protected CommunicationDispatcher $dispatcher,
    ) {
    }

    public function handle(TicketIssued $event): void
    {
        $order = $event->ticket->order;

        if (!$order) {
            return;
        }

        $recipient = User::where('id_anggota', $order->id_anggota)->first();

        $this->dispatcher->dispatch(
            'TicketIssued',
            $recipient?->id,
            CommunicationChannel::IN_APP,
            'ticket-issued',
            [
                'nomor_ticket' => $event->ticket->nomor_ticket,
                'event' => $order->event_name,
            ],
        );
    }
}