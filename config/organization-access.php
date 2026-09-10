<?php

return [
    'settings' => [
        1 => [
            'delete_user' => [
          //      'service_user' => false,
            ],
            'delete_service' => [
            //    'service_user' => false,
            ],
        ],
    ],

    'disabled_right_groups' => [
       // 1 => ['events', 'event_participants'],
    ],

    'right_groups' => [
        'users' => ['users.*'],
        'groups' => ['groups.*'],
        'rights' => ['rights.*'],
        'locations' => ['locations.*'],
        'services' => ['services.*', 'sms.view'],
        'articles' => ['articles.*'],
        'events' => ['events.*', 'event_participants.*'],
        'payments' => ['payments.*'],
        'custom-fields' => ['custom-fields.*'],
        'grades' => ['grades.*'],
    ],
];
