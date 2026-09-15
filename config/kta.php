<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public "Cek Status KTA" feature flag
    |--------------------------------------------------------------------------
    |
    | Defaults to DISABLED (fail-safe). The two public endpoints short-circuit
    | with a generic 503 until a deployment explicitly opts in by setting
    | KTA_PUBLIC_ENABLED=true in the environment. This keeps the feature dark
    | unless a release intentionally enables it.
    |
    */

    'enabled' => env('KTA_PUBLIC_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Challenge token
    |--------------------------------------------------------------------------
    |
    | The lookup endpoint returns an opaque, signed token that carries the
    | server-side lookup state (candidate id, matched stage, attempts left).
    | The token is never readable by the client and expires after `ttl`.
    | `bind_ip` / `bind_agent` tie the token to the requesting client so a
    | leaked token cannot be replayed from elsewhere.
    |
    */

    'token' => [
        'ttl' => (int) env('KTA_TOKEN_TTL', 600), // seconds
        'bind_ip' => true,
        'bind_agent' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification attempts
    |--------------------------------------------------------------------------
    |
    | How many wrong ownership answers a single challenge tolerates before the
    | token is locked. Mirrors the plan (5 attempts).
    |
    */

    'max_attempts' => (int) env('KTA_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Accepted date of birth range
    |--------------------------------------------------------------------------
    |
    | Any date outside this range is rejected before it reaches the query.
    | `1900_01_01` is the audit-derived lower bound; the sentinel values used
    | by legacy rows (0001-01-01) are additionally hard-rejected.
    |
    */

    'dob' => [
        'min' => '1900-01-01',
        'sentinels' => ['0001-01-01', '0000-00-00'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Disambiguation order
    |--------------------------------------------------------------------------
    |
    | When name+dob resolves to more than one member (61 such groups exist in
    | production) the client must answer these fields in order. The response
    | never reveals how many candidates exist.
    |
    */

    'disambiguate' => ['tahun_masuk', 'tempat_lahir', 'niqobah'],

    /*
    |--------------------------------------------------------------------------
    | Response timing floor (microseconds)
    |--------------------------------------------------------------------------
    |
    | A constant delay is added to lookup responses so `single`, `not_found`
    | and `ambiguous` cannot be told apart by latency. Set to 0 in tests.
    |
    */

    'response_delay_us' => (int) env('KTA_RESPONSE_DELAY_US', 300000),

    /*
    |--------------------------------------------------------------------------
    | Rate limits
    |--------------------------------------------------------------------------
    | Requests per minute, keyed by IP (unauthenticated public endpoint).
    */

    'rate_limit' => [
        'check' => (int) env('KTA_CHECK_PER_MINUTE', 5),
        'verify' => (int) env('KTA_VERIFY_PER_MINUTE', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Physical card status
    |--------------------------------------------------------------------------
    |
    | No print-tracking table exists yet, so every verified response reports
    | the physical card as `not_tracked`. Flip this only when a real source
    | of truth ships; until then the UI must say "belum tercatat".
    |
    */

    'physical_status' => 'not_tracked',

];
