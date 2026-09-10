<?php

return [
    'tokens' => [
        'expiration_minutes' => (int) env('BEARER_TOKEN_EXPIRATION_MINUTES', 10080),
        'password_setup_expiration_minutes' => (int) env('PASSWORD_SETUP_TOKEN_EXPIRATION_MINUTES', 1440),
    ],

    'rate_limits' => [
        'login_per_minute' => (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5),
        'callbacks_per_minute' => (int) env('RATE_LIMIT_CALLBACKS_PER_MINUTE', 60),
        'expensive_per_minute' => (int) env('RATE_LIMIT_EXPENSIVE_PER_MINUTE', 10),
    ],

    // The beneficiary can independently tune operator and administrator policy.
    'passwords' => [
        'operator' => [
            'min' => (int) env('OPERATOR_PASSWORD_MIN_LENGTH', 8),
            'letters' => env('OPERATOR_PASSWORD_LETTERS', false),
            'mixed_case' => env('OPERATOR_PASSWORD_MIXED_CASE', true),
            'numbers' => env('OPERATOR_PASSWORD_NUMBERS', true),
            'symbols' => env('OPERATOR_PASSWORD_SYMBOLS', false),
        ],
        'administrator' => [
            'min' => (int) env('ADMIN_PASSWORD_MIN_LENGTH', 8),
            'letters' => env('ADMIN_PASSWORD_LETTERS', false),
            'mixed_case' => env('ADMIN_PASSWORD_MIXED_CASE', true),
            'numbers' => env('ADMIN_PASSWORD_NUMBERS', true),
            'symbols' => env('ADMIN_PASSWORD_SYMBOLS', false),
        ],
    ],
];
