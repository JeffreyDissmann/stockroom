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

/*
|--------------------------------------------------------------------------
| Visual capture helpers
|--------------------------------------------------------------------------
|
| Used by VisualBaselineTest. They live here rather than inside it because
| Pest loads test files independently, so a helper defined in one file is
| undefined when another runs on its own — worth keeping global for the next
| screenshot suite rather than rediscovering it.
|
*/

function capturing(): bool
{
    return getenv('VISUAL_BASELINE') === '1';
}

/** Screenshots are named by run, so a second pass can't overwrite the first. */
function prefix(): string
{
    return getenv('SHOT_PREFIX') ?: 'before';
}
