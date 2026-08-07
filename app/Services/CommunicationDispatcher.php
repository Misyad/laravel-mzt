<?php

namespace App\Services;

use App\Communication\Providers\NullProvider;
use App\Enums\CommunicationChannel;
use App\Jobs\ProcessCommunication;
use App\Models\CommunicationLog;
use Illuminate\Support\Facades\Log;

/**
 * The single outbound gateway (ADR-016 "Single Communication Gateway").
 *
 * Orchestrator only (rule 1): it resolves template + recipient + channel and
 * delegates everything else — creating/logging the row, status transitions,
 * retries — to CommunicationLogService / the queue. It never selects a provider
 * directly (rule 2) and never contains business rules (those stay in domain
 * services).
 *
 * Any failure here is best-effort: a broken communication must never break the
 * business transaction that published the domain event.
 */
class CommunicationDispatcher
{
    public function __construct(
        protected TemplateService $templates,
        protected CommunicationLogService $logs,
    ) {
    }

    /**
     * Route a rendered communication through the engine.
     *
     * @param  string  $event     domain event class basename, e.g. "PaymentStatusChanged"
     * @param  int|null  $userId   recipient; when null no message is sent (no recipient)
     * @param  CommunicationChannel  $channel  the target channel (enum only)
     * @param  string  $template  template key from the config registry
     * @param  array<string,mixed>  $payload  placeholder data for the template
     * @return CommunicationLog|null  the created log (or null when no recipient)
     */
    public function dispatch(
        string $event,
        ?int $userId,
        CommunicationChannel $channel,
        string $template,
        array $payload = [],
    ): ?CommunicationLog {
        if ($userId === null) {
            return null;
        }

        try {
            // Render via the registry (rule 4) — no hard-coded templates here.
            $rendered = $this->templates->render($template, $channel, $payload);

            $provider = $this->resolveProviderName($channel);

            $log = $this->logs->create(
                $event,
                $userId,
                $channel,
                $provider,
                $template,
                $rendered['title'],
                $rendered['body'],
                $payload,
            );

            // Async (Queue-First). The job performs the actual delivery using
            // the channel resolver; the controller never sends anything.
            ProcessCommunication::dispatch($log->uuid)->onQueue(config('communication.queue', 'communications'));

            return $log;
        } catch (\Throwable $e) {
            // Best-effort: never let communication break the domain flow.
            Log::error('[Communication] dispatch failed', [
                'event' => $event,
                'template' => $template,
                'channel' => $channel->value,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Provider label stored on the log — from config, not hard-coded here.
     * Falls back to NullProvider for channels without a real adapter.
     */
    protected function resolveProviderName(CommunicationChannel $channel): string
    {
        $map = config('communication.channels', []);

        return (string) ($map[$channel->value] ?? NullProvider::class);
    }
}