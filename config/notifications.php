<?php

return [
    'subject' => env('NOTIFICATION_MAIL_SUBJECT', 'Notificare'),
    'templates' => [
        'service.activated' => 'Cotizația :service a fost activată.',
        'service.expiring' => 'Cotizația :service expiră la :expires_at.',
        'service.expired' => 'Cotizația :service a expirat la :expires_at.',
        'schedule.changed' => 'Programul pentru :event a fost modificat.',
        'announcement.urgent' => 'URGENT: :message',
        'activity.resumed' => 'Activitatea :event a fost reluată.',
    ],
];
