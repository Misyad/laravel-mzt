<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Paymenku webhook is signature-authenticated (HMAC SHA-256) and has no
        // session; CSRF does not apply.
        'api/webhooks/paymenku',
    ];
}
