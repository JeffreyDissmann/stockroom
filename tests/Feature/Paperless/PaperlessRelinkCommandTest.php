<?php

declare(strict_types=1);

use App\Jobs\RelinkAllPaperlessDocumentsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches a full relink by default', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    Queue::fake();

    $this->artisan('paperless:relink')->assertExitCode(0);

    Queue::assertPushed(RelinkAllPaperlessDocumentsJob::class, fn ($job) => $job->metadataOnly === false);
});

it('dispatches a metadata-only refresh with the flag', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    Queue::fake();

    $this->artisan('paperless:relink --metadata-only')->assertExitCode(0);

    Queue::assertPushed(RelinkAllPaperlessDocumentsJob::class, fn ($job) => $job->metadataOnly === true);
});

it('no-ops cleanly when Paperless is not configured', function () {
    config()->set('paperless.url', '');
    Queue::fake();

    // The daily scheduler must not error on installs without Paperless.
    $this->artisan('paperless:relink --metadata-only')->assertExitCode(0);

    Queue::assertNothingPushed();
});
