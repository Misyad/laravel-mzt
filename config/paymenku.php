<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paymenku payment gateway (official API)
    |--------------------------------------------------------------------------
    |
    | Base URL + Bearer API key. Sandbox uses `sk_test_...`, production uses
    | `sk_live_...`; only the key differs. Secrets are environment-only and are
    | never logged.
    |
    | Docs: https://docs.paymenku.com
    |   POST https://paymenku.com/api/v1/transaction/create
    |   GET  https://paymenku.com/api/v1/transaction/{trx_id}
    |
    */

    'base_url' => env('PAYMENKU_BASE_URL', 'https://paymenku.com/api/v1'),

    'api_key' => env('PAYMENKU_API_KEY'),

    /*
    | Webhook signature:
    |   X-PaymenKu-Signature = HMAC-SHA256(timestamp + "." + raw_body, secret)
    | Header X-PaymenKu-Timestamp carries the unix timestamp.
    */

    'webhook_secret' => env('PAYMENKU_WEBHOOK_SECRET'),

    'timeout' => (int) env('PAYMENKU_TIMEOUT', 20),

    /*
    | Retry/tolerance for webhook timestamps (seconds). Reject stale signatures
    | to blunt replay of captured requests.
    */

    'webhook_tolerance' => (int) env('PAYMENKU_WEBHOOK_TOLERANCE', 300),

];
