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
