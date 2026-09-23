<?php

return [
    'activation_enabled' => env('MEMBER_ACCOUNT_ACTIVATION_ENABLED', env('APP_ENV') === 'testing'),
    'applications_enabled' => env('MEMBER_APPLICATIONS_ENABLED', env('APP_ENV') === 'testing'),
    'frontend_url' => env('MEMBER_FRONTEND_URL', env('APP_URL', 'http://localhost')),
    'activation' => [
        'ttl_minutes' => (int) env('MEMBER_ACTIVATION_TTL_MINUTES', 10),
        'attempts' => (int) env('MEMBER_ACTIVATION_ATTEMPTS', 5),
        'response_delay_us' => (int) env('MEMBER_ACTIVATION_RESPONSE_DELAY_US', 250000),
    ],
    'email_verification' => [
        'ttl_minutes' => (int) env('MEMBER_EMAIL_CODE_TTL_MINUTES', 10),
        'attempts' => (int) env('MEMBER_EMAIL_CODE_ATTEMPTS', 5),
    ],
    'password_reset' => [
        'ttl_minutes' => (int) env('MEMBER_PASSWORD_RESET_TTL_MINUTES', 30),
    ],
    'rate_limit' => [
        'activation_check' => (int) env('MEMBER_ACTIVATION_CHECK_PER_MINUTE', 5),
        'activation_verify' => (int) env('MEMBER_ACTIVATION_VERIFY_PER_MINUTE', 10),
        'setup' => (int) env('MEMBER_ACCOUNT_SETUP_PER_MINUTE', 6),
        'password_forgot' => (int) env('MEMBER_PASSWORD_FORGOT_PER_MINUTE', 5),
        'password_reset' => (int) env('MEMBER_PASSWORD_RESET_PER_MINUTE', 8),
        'application' => (int) env('MEMBER_APPLICATIONS_PER_HOUR', 5),
        'applicant_login' => (int) env('MEMBER_APPLICANT_LOGIN_PER_MINUTE', 5),
        'applicant_email' => (int) env('MEMBER_APPLICANT_EMAIL_PER_MINUTE', 6),
    ],
];
