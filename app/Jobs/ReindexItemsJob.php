<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-index a specific list of items in Scout. Used by BulkController to
 * push the slow part of bulk operations (Ollama embedding generation
 * + Meilisearch upsert) off the request thread.
 *
 * Items move / get retagged in the request itself (DB write is cheap);
 * the index update lands a few seconds later when the worker picks this
 * up. The browse UI uses direct Eloquent queries, so the items appear
 * in their new location immediately — only the search box has the
 * brief eventual-consistency window.
 *
 * Tries(1) — the data the index will rebuild from is already on disk;
 * a transient queue worker hiccup doesn't need to be retried, the next
 * write to the same item will pick the changes up anyway.
 */
#[Tries(1)]
class ReindexItemsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  list<int>  $itemIds
     */
    public function __construct(public readonly array $itemIds) {}

    public function handle(): void
    {
        if ($this->itemIds === []) {
            return;
        }

        // chunkById so a 500-item bulk move doesn't pull everything into
        // memory at once; each chunk is its own searchable() batch which
        // is one bulk PUT to Meilisearch + one bulk embedding fetch.
        Item::query()
            ->whereIn('id', $this->itemIds)
            ->chunkById(50, fn ($chunk) => $chunk->searchable());
    }
}
