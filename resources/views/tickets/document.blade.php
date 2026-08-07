<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; color: #1e293b; }
        .page { width: 100%; padding: 24px; border: 2px solid #0f766e; border-radius: 12px; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 22px; font-weight: 700; color: #0f766e; margin: 0 0 2px; }
        .subtitle { font-size: 12px; color: #475569; margin: 0; }
        .event-name { font-size: 18px; font-weight: 600; margin: 0 0 4px; }
        .meta { margin-top: 8px; font-size: 12px; color: #334155; }
        .meta div { margin: 2px 0; }
        .footer { display: flex; justify-content: space-between; align-items: center; margin-top: 16px; border-top: 1px dashed #cbd5e1; padding-top: 12px; }
        .ticket-code { font-size: 16px; font-weight: 700; letter-spacing: 1px; color: #0f766e; }
        .qr img { width: 128px; height: 128px; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .status-issued { background: #ecfdf5; color: #047857; }
        .status-checked_in { background: #e0f2fe; color: #0369a1; }
        .status-others { background: #f1f5f9; color: #475569; }
        .note { font-size: 10px; color: #94a3b8; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <p class="title">E-Ticket</p>
            <p class="subtitle">Maziltu Tholiban Members Platform</p>
        </div>

        <p class="event-name">{{ $order->event_name ?? '-' }}</p>

        <div class="meta">
            <div>Nama Pemegang Tiket : {{ $order->id_anggota ?? '-' }}</div>
            <div>Nomor Order : #{{ $order->nomor_order ?? '-' }}</div>
            <div>Tanggal Event : {{ optional($order->event_start_at)->format('d-m-Y') }}</div>
            <div>Diterbitkan : {{ optional($ticket->issued_at)->format('d-m-Y H:i') }}</div>
        </div>

        <div class="footer">
            <div>
                <div class="ticket-code">{{ $ticket->nomor_ticket }}</div>
                <span class="status-badge status-{{ $ticket->status }}">{{ $ticket->status }}</span>
            </div>
            <div class="qr">
                <img src="{{ $qr_data_uri }}" alt="QR">
            </div>
        </div>

        <div class="note">
            Ticket ini sah berdasarkan UUID: {{ $ticket->uuid }}. Sertakan kode ini saat check-in.
        </div>
    </div>
</body>
</html>