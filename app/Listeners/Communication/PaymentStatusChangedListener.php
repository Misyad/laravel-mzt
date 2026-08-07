<?php

namespace App\Listeners\Communication;

use App\Enums\CommunicationChannel;
use App\Enums\PaymentStatus;
use App\Events\PaymentStatusChanged;
use App\Models\User;
use App\Services\CommunicationDispatcher;

/**
 * Forwards PaymentStatusChanged to the Communication Engine (forward-only).
 *
 * No business logic here: it merely maps the event to a template + channel,
 * resolves the recipient, and hands the payload to the dispatcher. All rules
 * about *when* a payment may change status live in the payment domain services.
 */
class PaymentStatusChangedListener
{
    public function __construct(
        protected CommunicationDispatcher $dispatcher,
    ) {
    }

    public function handle(PaymentStatusChanged $event): void
    {
        $template = match ($event->newStatus) {
            PaymentStatus::PAID->value => 'payment-approved',
            PaymentStatus::REJECTED->value => 'payment-rejected',
            default => null,
        };

        if ($template === null) {
            return;
        }

        $order = $event->payment->order;

        if (!$order) {
            return;
        }

        $recipient = User::where('id_anggota', $order->id_anggota)->first();

        $this->dispatcher->dispatch(
            'PaymentStatusChanged',
            $recipient?->id,
            CommunicationChannel::IN_APP,
            $template,
            [
                'nomor_payment' => $event->payment->nomor_payment,
                'jumlah' => number_format((float) $event->payment->amount, 0, ',', '.'),
                'event' => $order->event_name,
            ],
        );
    }
}