<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Item;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Rebuilds the whole search index in the background — re-pushing every item to
 * Scout, which regenerates its embedding (unchanged item text is served from
 * the embedding cache). Progress is reported via the cache so the household UI
 * can show a live progress bar.
 *
 * The rebuild starts from an EMPTY index: Meilisearch refuses to register a
 * `userProvided` embedder while the index still holds documents without
 * vectors, so flushing first (then re-pushing the index settings) is what
 * lets semantic search be enabled on an already-populated install.
 */
#[Timeout(1800)]
#[Tries(1)]
class RebuildSearchIndexJob implements ShouldQueue
{
    use Queueable;

    public const STATUS_KEY = 'search.reindex';

    public function handle(): void
    {
        $total = Item::count();

        $this->putStatus(['state' => 'running', 'done' => 0, 'total' => $total]);

        // Index inline even when SCOUT_QUEUE is on — this already IS the
        // background job, and fanning out per-chunk MakeSearchable jobs would
        // report "done" while embedding still sits in the queue.
        config(['scout.queue' => false]);

        Item::removeAllFromSearch();

        if (config('scout.driver') === 'meilisearch') {
            Artisan::call('scout:sync-index-settings');
        }

        $done = 0;

        Item::query()->chunkById(50, function (Collection $items) use (&$done, $total): void {
            $items->searchable();
            $done += $items->count();

            $this->putStatus(['state' => 'running', 'done' => $done, 'total' => $total]);
        });

        $this->putStatus(['state' => 'done', 'total' => $total, 'indexed' => $done]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->putStatus(['state' => 'failed', 'error' => $exception?->getMessage() ?? 'Reindex failed.']);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function putStatus(array $status): void
    {
        Cache::put(self::STATUS_KEY, $status, now()->addHour());
    }
}
