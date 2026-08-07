<?php

namespace App\Services;

use App\Enums\CommunicationChannel;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Notification Center query layer (owner-scoped).
 *
 * Notifications are the in-app communication_logs (channel = IN_APP). Read
 * state is tracked with read_at. All access is scoped to `user_id === $user`
 * (owner) — enforced here and again in the controller/policy (rule 4).
 *
 * "Delivered" for an in-app notification means the row exists with
 * status DELIVERED; we expose both delivered and still-queued rows to the
 * inbox for transparency.
 */
class NotificationService
{
    public function __construct(
        protected CommunicationLogService $logs,
    ) {
    }

    /**
     * Paginated inbox for a user.
     *
     * @param  array{unread?: bool, per_page?: int, page?: int}  $options
     */
    public function forUser(User $user, array $options = []): LengthAwarePaginator
    {
        $perPage = (int) ($options['per_page'] ?? 20);
        $unreadOnly = (bool) ($options['unread'] ?? false);

        $query = CommunicationLog::query()
            ->where('user_id', $user->id)
            ->where('channel', CommunicationChannel::IN_APP->value)
            ->orderByDesc('id');

        if ($unreadOnly) {
            $query->whereNull('read_at');
        }

        return $query->paginate($perPage, ['*'], 'page', $options['page'] ?? null)
            ->withQueryString();
    }

    /**
     * Number of unread in-app notifications for a user.
     */
    public function unreadCount(User $user): int
    {
        return CommunicationLog::query()
            ->where('user_id', $user->id)
            ->where('channel', CommunicationChannel::IN_APP->value)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Mark a single notification read. Enforces ownership.
     */
    public function markRead(User $user, string $uuid): bool
    {
        $log = CommunicationLog::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->where('channel', CommunicationChannel::IN_APP->value)
            ->first();

        if (!$log) {
            return false;
        }

        $this->logs->markRead($log);

        return true;
    }

    /**
     * Mark all of a user's unread in-app notifications as read.
     */
    public function markAllRead(User $user): int
    {
        return CommunicationLog::query()
            ->where('user_id', $user->id)
            ->where('channel', CommunicationChannel::IN_APP->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}