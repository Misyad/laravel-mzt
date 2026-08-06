<?php

namespace App\Events;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched whenever a payment's status changes (ADR-016 / plan §5.11).
 *
 * Consumers in beta1: none yet — Sprint 4 wires the Communication Engine
 * (SendPaymentApproved...) to this event via the database queue.
 */
class PaymentStatusChanged
{
    use Dispatchable, SerializesModels;

    /**
     * @param  Payment  $payment  the payment that changed
     * @param  string  $oldStatus  previous status value
     * @param  string  $newStatus  new status value
     * @param  \App\Models\User|null  $actor  user who caused the change
     */
    public function __construct(
        public Payment $payment,
        public string $oldStatus,
        public string $newStatus,
        public ?User $actor = null,
        public ?string $note = null,
    ) {
    }
}