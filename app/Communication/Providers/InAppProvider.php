<?php

namespace App\Communication\Providers;

use App\Communication\CommunicationProviderInterface;
use App\Enums\CommunicationChannel;
use App\Models\CommunicationLog;

/**
 * Real provider for the in-app channel.
 *
 * Delivery IS the persisted Communication Log row — the Notification Center
 * renders it directly from the log. No external hand-off: returning true tells
 * the job to mark the log DELIVERED. Lifecycle (status/delivered_at) is still
 * owned by CommunicationLogService (rule 1), never by this provider.
 */
class InAppProvider implements CommunicationProviderInterface
{
    public function send(CommunicationLog $log, array $payload): bool
    {
        return true;
    }

    public function channel(): CommunicationChannel
    {
        return CommunicationChannel::IN_APP;
    }
}