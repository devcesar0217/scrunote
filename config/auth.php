<?php

use App\Models\User;

return [
    // Disable public signup after provisioning accounts when appropriate.
    'registration_enabled' => env('AUTH_REGISTRATION_ENABLED', true),

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    // Null prevents Laravel's framework defaults from adding a reset broker.
    'passwords' => null,

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
    ],
];
