<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    // Explicit origins only — never `*`, because `supports_credentials` is true
    // (the browser session is cookie-based). Development frontend runs on
    // http://localhost:8080 (Lovable Vite config). Production is same-origin via
    // Caddy, so no cross-origin entry is needed there.
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080,http://127.0.0.1:8080')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];

