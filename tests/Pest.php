<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature and Browser tests both bind to the application's TestCase and
| run against a fresh database. Browser tests live in tests/Browser and
| are driven by the Pest browser plugin (Playwright).
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

// Browser interactions (image upload + resize, multipart submits) can exceed the
// 5s default; give them headroom.
pest()->browser()->timeout(15000);
