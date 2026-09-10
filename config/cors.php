<?php

return [
    'paths' => [
        'api/*',
        'docs',
        'api/documentation',
        'storage/api-docs/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'https://manager.befu.ro,https://uur.spa360.ro,https://uur.spa360lro'
        ))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];