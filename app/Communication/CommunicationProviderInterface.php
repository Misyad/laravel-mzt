<?php

namespace App\Communication;

use App\Models\CommunicationLog;

/**
 * Adapter contract for a communication provider (ADR-016 Provider Layer).
 *
 * Domain layer never talks to providers; the ChannelResolver maps a
 * CommunicationChannel to a concrete provider. Sprint 4 ships InAppProvider
 * (real) and NullProvider (stub for every external channel).
 */
interface CommunicationProviderInterface
{
    /**
     * Actually hand the rendered communication to the recipient.
     *
     * @param  array<string,mixed>  $payload  rendered payload (title/body/context)
     * @return bool  whether the channel accepted the delivery
     */
    public function send(CommunicationLog $log, array $payload): bool;

    /**
     * The channel this provider serves.
     */
    public function channel(): \App\Enums\CommunicationChannel;
}