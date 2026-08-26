<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Forget idle assistant conversations daily (window from ai.chat_retention_days).
Schedule::command('ai:forget-conversations')->dailyAt('03:15');

// Morning maintenance digest for opted-in users; sends nothing when no
// task is overdue or inside its reminder window.
Schedule::command('maintenance:send-digest')->dailyAt('07:00');

// Refresh the cached Paperless document metadata (title/type) daily so
// renames in Paperless surface in Stockroom on their own. Metadata-only:
// no tag/backlink writes back to Paperless — the full relink stays manual.
Schedule::command('paperless:relink --metadata-only')->dailyAt('04:30');

// Look at item photos nobody has reviewed and record catalogue proposals.
//
// 02:30 because vision inference is the heaviest thing this app asks of its
// hardware, and it shares that hardware with whatever else the household runs.
// withoutOverlapping guards a run that outlives the hour — twenty items with
// several photos each takes a while at fifteen seconds a photo — and the
// command itself stands down if the queue is busy. runInBackground keeps a slow
// run from delaying the digest at 07:00.
Schedule::command('items:review-photos --limit=20')
    ->dailyAt('02:30')
    ->withoutOverlapping(120)
    ->runInBackground();
