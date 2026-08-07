<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketActionRequest;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\TicketDocumentService;
use App\Services\TicketLifecycleService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ticket Engine API (PRD §17.7 / §21.7).
 *
 * Endpoints:
 *  - GET  /api/orders/{uuid}/ticket           : my ticket for an order (owner/staff)
 *  - GET  /api/tickets/{uuid}               : ticket detail (owner/staff)
 *  - GET  /api/tickets/{uuid}/download      : PDF with on-demand QR (owner/staff)
 *  - POST /api/tickets/{uuid}/reissue       : re-issue (Finance/Admin)
 *  - DELETE /api/tickets/{uuid}             : revoke (Finance/Admin)
 */
class TicketController extends Controller
{
    public function __construct(
        protected TicketService $ticket,
        protected TicketLifecycleService $lifecycle,
        protected TicketDocumentService $documents,
    ) {
    }

    /**
     * My ticket for a given order.
     */
    public function myTicket(Request $request, $orderUuid)
    {
        $order = Order::where('uuid', $orderUuid)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
        }

        $ticket = $order->tickets()->latest('id')->first();
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket belum diterbitkan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('view', $ticket)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json(['success' => true, 'data' => ['ticket' => $ticket]]);
    }

    /**
     * Ticket detail.
     */
    public function show(Request $request, $uuid)
    {
        $ticket = Ticket::where('uuid', $uuid)->first();
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('view', $ticket)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => $ticket,
            ],
        ]);
    }

    /**
     * Download the ticket document as a PDF (QR on demand, never stored).
     */
    public function download(Request $request, $uuid)
    {
        $ticket = Ticket::where('uuid', $uuid)->first();
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('download', $ticket)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $filename = 'tiket-' . $ticket->nomor_ticket . '.pdf';

        return response($this->documents->pdf($ticket), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Re-issue a ticket (identity preserved, on-demand document re-render).
     */
    public function reissue(TicketActionRequest $request, $uuid)
    {
        $ticket = Ticket::where('uuid', $uuid)->first();
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('reissue', $ticket)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $result = $this->lifecycle->reissue($request->user(), $ticket, $request->note);

        if (!$result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json(['success' => true, 'data' => ['ticket' => $result['ticket']]], $result['code']);
    }

    /**
     * Revoke a ticket.
     */
    public function revoke(TicketActionRequest $request, $uuid)
    {
        $ticket = Ticket::where('uuid', $uuid)->first();
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan'], 404);
        }

        if (!Gate::forUser($request->user())->allows('revoke', $ticket)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $result = $this->lifecycle->revoke($request->user(), $ticket, $request->note);

        if (!$result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json(['success' => true, 'data' => ['ticket' => $result['ticket']]], $result['code']);
    }
}