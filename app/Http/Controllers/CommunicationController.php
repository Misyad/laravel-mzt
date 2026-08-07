<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationReadRequest;
use App\Models\CommunicationLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Notification Center + Communication Log API (Sprint 4).
 *
 * Access split (rule 4):
 *  - /notifications*  -> owner only (scoped in NotificationService)
 *  - /communication-logs -> admin/staff via CommunicationLogPolicy (read-only)
 *
 * No controller ever dispatches a notification — writing is done exclusively by
 * the Communication Engine (domain events + queue).
 */
class CommunicationController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
    ) {
    }

    /**
     * GET /api/notifications — the current user's in-app inbox.
     */
    public function index(Request $request)
    {
        $data = $this->notifications->forUser($request->user(), [
            'unread' => filter_var($request->query('unread', false), FILTER_VALIDATE_BOOL),
            'per_page' => (int) ($request->query('per_page', 20) ?? 20),
            'page' => (int) ($request->query('page', 1) ?? 1),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $data->items(),
                'unread_count' => $this->notifications->unreadCount($request->user()),
                'pagination' => [
                    'total' => $data->total(),
                    'per_page' => $data->perPage(),
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * PUT /api/notifications/read — mark one notification read (owner).
     */
    public function markRead(NotificationReadRequest $request)
    {
        $ok = $this->notifications->markRead($request->user(), $request->validated('uuid'));

        if (!$ok) {
            return response()->json(['success' => false, 'message' => 'Notification tidak ditemukan'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Notification ditandai sudah dibaca']);
    }

    /**
     * PATCH /api/notifications/read-all — mark all of the user's unread read.
     */
    public function markAllRead(Request $request)
    {
        $count = $this->notifications->markAllRead($request->user());

        return response()->json(['success' => true, 'data' => ['marked' => $count]]);
    }

    /**
     * GET /api/communication-logs — audit trail (admin/staff only).
     */
    public function communicationLogs(Request $request)
    {
        $log = new CommunicationLog();
        if (!Gate::forUser($request->user())->allows('view', $log)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $query = CommunicationLog::query()->orderByDesc('id');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->input('channel'));
        }

        $rows = $query->paginate((int) ($request->input('per_page', 20) ?? 20));

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $rows->items(),
                'pagination' => [
                    'total' => $rows->total(),
                    'per_page' => $rows->perPage(),
                    'current_page' => $rows->currentPage(),
                    'last_page' => $rows->lastPage(),
                ],
            ],
        ]);
    }
}