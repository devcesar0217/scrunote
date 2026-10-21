<?php

return [
    // This application does not send authentication email. Keep only Laravel's
    // in-memory transport; no outbound mail transport or credentials are used.
    'default' => 'array',

    'mailers' => [
        'array' => [
            'transport' => 'array',
        ],
    ],

    'from' => [
        'address' => 'no-reply@example.invalid',
        'name' => env('APP_NAME', 'Scrunote'),
    ],
];
