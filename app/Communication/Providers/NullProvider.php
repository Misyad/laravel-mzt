<?php

namespace App\Communication\Providers;

use App\Communication\CommunicationProviderInterface;
use App\Enums\CommunicationChannel;
use App\Models\CommunicationLog;

/**
 * Stub provider for external channels not yet implemented (email, whatsapp,
 * telegram, discord, push). It intentionally does nothing over the wire so
 * Sprint 4 never actually sends Email/WhatsApp/etc. — it only "accepts" the
 * delivery so the job can record DELIVERED and the audit trail stays correct.
 *
 * Swap in a real adapter behind this interface later without touching the
 * domain layer or the dispatcher.
 */
class NullProvider implements CommunicationProviderInterface
{
    public function __construct(
        protected CommunicationChannel $channel,
    ) {
    }

    public function send(CommunicationLog $log, array $payload): bool
    {
        return true;
    }

    public function channel(): CommunicationChannel
    {
        return $this->channel;
    }
}