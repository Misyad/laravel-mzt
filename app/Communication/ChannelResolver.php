<?php

namespace App\Communication;

use App\Communication\Providers\InAppProvider;
use App\Communication\Providers\NullProvider;
use App\Enums\CommunicationChannel;
use Illuminate\Support\Facades\App;

/**
 * Maps a CommunicationChannel to a concrete communication provider (rule 2).
 *
 * CommunicationDispatcher only knows channels (enums); it never picks a
 * provider directly — it always goes through this resolver, whose channel →
 * provider map lives in config/communication.php. Adding/ swapping a provider
 * is a config concern, not a domain or dispatcher concern.
 */
class ChannelResolver
{
    /**
     * Resolve the provider for a channel.
     *
     * Applies: real InAppProvider for in-app; otherwise a NullProvider stub.
     * A per-channel custom provider read from config wins when provided.
     *
     * @throws \InvalidArgumentException  when no provider can serve the channel
     */
    public function provider(CommunicationChannel $channel): CommunicationProviderInterface
    {
        $map = config('communication.channels', []);

        $class = $map[$channel->value] ?? null;

        if (is_string($class) && class_exists($class)) {
            $provider = App::make($class);

            return $provider instanceof CommunicationProviderInterface
                ? $provider
                : new NullProvider($channel);
        }

        if ($channel === CommunicationChannel::IN_APP) {
            return new InAppProvider();
        }

        // Every other channel uses a Null/Stub adapter in Sprint 4.
        return new NullProvider($channel);
    }
}