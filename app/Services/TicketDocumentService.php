<?php

namespace App\Services;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade as PDF;
use Milon\Barcode\Facades\DNS2DFacade as DNS2D;

/**
 * Renders a ticket document (PDF) with an on-demand QR (PRD §21.7 / §10.6).
 *
 * The QR is the ticket's qr_payload (the ticket UUID only). It is generated
 * on the fly inside the PDF and never stored as a permanent image file — reissue
 * simply re-renders the same document on demand.
 */
class TicketDocumentService
{
    public function __construct()
    {
    }

    /**
     * Base64 PNG of the ticket's QR code (on-demand, nothing persisted).
     *
     * @return string data-uri ready base64 PNG
     */
    public function qrBase64(Ticket $ticket): string
    {
        $payload = (string) $ticket->qr_payload;

        return (string) DNS2D::getBarcodePNG($payload, 'QRCODE', 3, 3, [0, 0, 0]);
    }

    /**
     * Render the ticket as an on-demand PDF document.
     *
     * @return string PDF binary content
     */
    public function pdf(Ticket $ticket): string
    {
        $order = $ticket->order;
        $qrData = $this->qrBase64($ticket);

        $html = view('tickets.document', [
            'ticket' => $ticket,
            'order' => $order,
            'qr_data_uri' => 'data:image/png;base64,' . $qrData,
        ])->render();

        return PDF::loadHTML($html)->output();
    }
}