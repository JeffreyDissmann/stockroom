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

    /*
     | Build provenance: the CalVer tag and commit SHA the running image
     | was built from. The release workflow passes these as Docker
     | --build-arg values so they survive `config:cache` (env() returns
     | null in cached config — surfacing them here is what makes them
     | reachable at request time). Either may be empty in dev; the
     | login-page version chip hides itself in that case.
     */
    'version' => [
        'tag' => env('APP_VERSION'),
        'commit' => env('APP_COMMIT'),
    ],

    /*
     | Battery tracking. `low_threshold` is the household-wide percent at or
     | below which a battery counts as low (one global default for v1, not
     | per-item). The `change_detection` rule auto-detects a swap from the
     | reading stream Home Assistant pushes: a jump up to `min_percent` or
     | higher that also rises by at least `min_jump` points reads as a fresh
     | battery, so we close the old cycle and open a new one automatically.
     */
    'battery' => [
        'low_threshold' => (int) env('BATTERY_LOW_THRESHOLD', 20),
        'change_detection' => [
            'min_percent' => 90,
            'min_jump' => 50,
        ],
    ],
];
