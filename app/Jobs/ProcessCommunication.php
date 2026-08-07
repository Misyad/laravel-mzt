<?php

namespace App\Jobs;

use App\Communication\ChannelResolver;
use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\CommunicationLog;
use App\Services\CommunicationLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a single outbound communication asynchronously (Queue-First).
 *
 * Idempotent (rule 5): when the Communication Log is already DELIVERED, the job
 * immediately stops without sending again; a FAILED (final) log is never
 * re-sent either. Lifecycle transitions are delegated to CommunicationLogService
 * (rule 1) and the provider is resolved through ChannelResolver (rule 2).
 */
class ProcessCommunication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of auto-retries before the job lands in failed_jobs.
     */
    public int $tries = 3;

    public function __construct(
        public string $logUuid,
    ) {
    }

    public function handle(CommunicationLogService $logs, ChannelResolver $resolver): void
    {
        $log = CommunicationLog::where('uuid', $this->logUuid)->first();

        if (!$log) {
            return;
        }

        // Idempotency guard (rule 5): never re-send an already delivered log,
        // and never resurrect a final FAILED state.
        if (in_array($log->status, [CommunicationStatus::DELIVERED->value, CommunicationStatus::FAILED->value], true)) {
            return;
        }

        $logs->markProcessing($log);

        $channel = CommunicationChannel::tryFrom($log->channel) ?? CommunicationChannel::IN_APP;
        $provider = $resolver->provider($channel);

        $payload = $log->payload && is_array($log->payload) ? $log->payload : [];

        // Throwing here lets the queue auto-retry (tries=3); `failed()` below
        // records the final FAILED state (CommunicationLogService, rule 1).
        if (!$provider->send($log, $payload)) {
            throw new \RuntimeException('Provider send returned false for log ' . $this->logUuid);
        }

        $logs->markDelivered($log);
    }

    /**
     * Runs once retries are exhausted — record the log as FAILED.
     */
    public function failed(Throwable $exception): void
    {
        $log = CommunicationLog::where('uuid', $this->logUuid)->first();

        if (!$log) {
            return;
        }

        $logs = app(CommunicationLogService::class);
        $logs->incrementRetry($log);
        $logs->markFailed($log, 'Exception: ' . substr((string) $exception->getMessage(), 0, 1000));

        Log::error('[Communication] delivery failed', [
            'uuid' => $this->logUuid,
            'error' => $exception->getMessage(),
        ]);
    }
}