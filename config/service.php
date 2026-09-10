<?php

return [
    'expiration_notice_days' => (int) env('SERVICE_EXPIRATION_NOTICE_DAYS', 1),

    'expiration_notice_message' => env(
        'SERVICE_EXPIRATION_NOTICE_MESSAGE',
        'Serviciul :service expira la data de :expires_at.'
    ),
];
