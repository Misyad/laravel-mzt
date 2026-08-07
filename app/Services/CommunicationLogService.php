<?php

namespace App\Services;

use App\Enums\CommunicationChannel;
use App\Enums\CommunicationStatus;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Single authority over the Communication Log lifecycle (rule 1).
 *
 * Immutable (rule 3): rows are only ever created once and never deleted or
 * overwritten. The only allowed mutation is the status progression
 * QUEUED → PROCESSING → DELIVERED | FAILED, plus retry_count (on failure) and
 * read_at (owner marking an in-app notification as read). No other column may
 * change after creation.
 *
 * CommunicationDispatcher orchestrates but never touches the log rows itself;
 * it always routes through this service.
 */
class CommunicationLogService
{
    /**
     * Create the initial (QUEUED) log row. Immutable afterwards — the caller
     * must pass the already-rendered title/message/payload.
     *
     * @param  array<string,mixed>  $payload
     */
    public function create(
        string $event,
        ?int $userId,
        CommunicationChannel $channel,
        string $provider,
        string $template,
        string $title,
        string $message,
        array $payload = [],
    ): CommunicationLog {
        return CommunicationLog::create([
            'uuid' => (string) Str::uuid(),
            'event' => $event,
            'user_id' => $userId,
            'channel' => $channel->value,
            'provider' => $provider,
            'template' => $template,
            'status' => CommunicationStatus::QUEUED->value,
            'title' => $title,
            'message' => $message,
            'payload' => $payload,
            'retry_count' => 0,
            'created_at' => now(),
            'delivered_at' => null,
        ]);
    }

    /**
     * Move a log from QUEUED to PROCESSING (stays PROCESSING when a retry
     * re-enters the job after a partial attempt). No-op for terminal states.
     */
    public function markProcessing(CommunicationLog $log): void
    {
        if (in_array($log->status, [CommunicationStatus::DELIVERED->value, CommunicationStatus::FAILED->value], true)) {
            return;
        }

        $log->forceFill(['status' => CommunicationStatus::PROCESSING->value])->save();
    }

    /**
     * Mark a log as DELIVERED. Idempotent: an already-delivered log is left
     * untouched (this is the in-job guard for the idempotency rule 5).
     */
    public function markDelivered(CommunicationLog $log): void
    {
        if ($log->status === CommunicationStatus::DELIVERED->value) {
            return;
        }

        $log->forceFill([
            'status' => CommunicationStatus::DELIVERED->value,
            'delivered_at' => $log->delivered_at ?? now(),
        ])->save();
    }

    /**
     * Mark a log as FAILED after retries are exhausted, recording the reason.
     */
    public function markFailed(CommunicationLog $log, ?string $response = null): void
    {
        if ($log->status === CommunicationStatus::FAILED->value) {
            return;
        }

        $log->forceFill([
            'status' => CommunicationStatus::FAILED->value,
            'response' => $response ?: $log->response,
        ])->save();
    }

    /**
     * Increment the retry counter for a log (called on a job attempt).
     */
    public function incrementRetry(CommunicationLog $log): void
    {
        $log->forceFill(['retry_count' => (int) $log->retry_count + 1])->save();
    }

    /**
     * Mark an in-app notification as read by its owner.
     */
    public function markRead(CommunicationLog $log): void
    {
        if ($log->read_at !== null) {
            return;
        }

        $log->forceFill(['read_at' => now()])->save();
    }

    /**
     * Find a log by its public uuid, scoped to a user when requested.
     */
    public function findByUuid(string $uuid, ?int $userId = null): ?CommunicationLog
    {
        $query = CommunicationLog::where('uuid', $uuid);

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }
}