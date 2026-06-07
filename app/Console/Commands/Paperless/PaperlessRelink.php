<?php

declare(strict_types=1);

namespace App\Console\Commands\Paperless;

use App\Jobs\RelinkAllPaperlessDocumentsJob;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * CLI entry point for the relink job — the same operation as the
 * "Repair Paperless links" button, runnable from cron/scheduler.
 *
 * `--metadata-only` refreshes the cached title/type/correspondent snapshot
 * without touching Paperless (no tag/backlink writes). That read-only
 * variant is what the daily scheduler runs (see routes/console.php), so
 * renames in Paperless surface in Stockroom within a day; the full relink
 * stays a manual action.
 */
#[Signature('paperless:relink {--metadata-only : Refresh cached document metadata only, without re-applying Paperless tags/backlinks}')]
#[Description('Re-apply Stockroom tags/backlinks on every linked Paperless document (or, with --metadata-only, just refresh cached titles/types)')]
class PaperlessRelink extends Command
{
    public function handle(): int
    {
        // No-op cleanly when the integration is off so the daily schedule
        // doesn't error on installs without Paperless.
        if (PaperlessClient::fromConfig() === null) {
            $this->info('Paperless integration is not configured — nothing to do.');

            return self::SUCCESS;
        }

        $metadataOnly = (bool) $this->option('metadata-only');

        RelinkAllPaperlessDocumentsJob::dispatch($metadataOnly);

        $this->info($metadataOnly
            ? 'Dispatched Paperless metadata refresh.'
            : 'Dispatched Paperless relink (tags + backlinks + metadata).');

        return self::SUCCESS;
    }
}
