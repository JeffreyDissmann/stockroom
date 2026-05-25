<?php

declare(strict_types=1);

return [
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@stockroom.local'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

    /*
     | One currency for the whole household. `code` is an ISO 4217 code
     | (USD, EUR, GBP, …); `locale` controls how amounts are formatted
     | (symbol placement, grouping, decimals) via Intl.NumberFormat.
     */
    'currency' => [
        'code' => env('CURRENCY', 'USD'),
        'locale' => env('CURRENCY_LOCALE', 'en-US'),
    ],
];
