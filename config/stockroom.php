<?php

declare(strict_types=1);

return [
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@stockroom.local'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],
];
