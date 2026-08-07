<?php

use App\Communication\Providers\InAppProvider;

// =============================================================================
// Communication Engine configuration (ADR-016)
// =============================================================================
// Central source of truth for:
//  - channel -> provider mapping (consumed by ChannelResolver, rule 2) so the
//    dispatcher never picks a provider directly.
//  - the Template Registry (consumed by TemplateService, rule 4) - templates
//    must be added here, never hard-coded in a service.
//
// Template shape: { title, body, placeholders[] }.
// Body/title use `{{placeholder}}` substitution. Payload keys are supplied by
// the listener/dispatcher and must never contain secrets.
// =============================================================================

return [

    'queue' => 'communications',

    'retry_tries' => 3,

    'channels' => [
        'in-app' => InAppProvider::class,
        // 'email'      => \App\Communication\Providers\NullProvider::class,
        // 'whatsapp'   => \App\Communication\Providers\NullProvider::class,
        // External channels have no real provider in Sprint 4 (Null/Stub is used).
    ],

    'templates' => [

        'payment-approved' => [
            'title' => 'Pembayaran {{nomor_payment}} disetujui',
            'body' => 'Pembayaran sebesar {{jumlah}} untuk "{{event}}" telah disetujui. ' .
                      'Terima kasih atas pembayaran Anda.',
            'placeholders' => ['nomor_payment', 'jumlah', 'event'],
        ],

        'payment-rejected' => [
            'title' => 'Pembayaran {{nomor_payment}} ditolak',
            'body' => 'Pembayaran untuk "{{event}}" ditolak. Silakan hubungi panitia.',
            'placeholders' => ['nomor_payment', 'event'],
        ],

        'ticket-issued' => [
            'title' => 'Tiket {{nomor_ticket}} diterbitkan',
            'body' => 'Tiket Anda untuk "{{event}}" telah diterbitkan. Nomor tiket: {{nomor_ticket}}.',
            'placeholders' => ['nomor_ticket', 'event'],
        ],

        'ticket-revoked' => [
            'title' => 'Tiket {{nomor_ticket}} dibatalkan',
            'body' => 'Tiket Anda untuk "{{event}}" ({{nomor_ticket}}) telah dibatalkan.',
            'placeholders' => ['nomor_ticket', 'event'],
        ],
    ],
];