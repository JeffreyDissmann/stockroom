<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\BulkRequest;
use App\Jobs\ReindexItemsJob;
use App\Models\Item;
use App\Services\Items\ItemWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Multi-item operations from the bulk-select UI on /items and /search.
 *
 * The four supported actions — delete, move, attach-tag, detach-tag —
 * share one endpoint instead of fanning out into four routes because the
 * frontend's selection state and action bar are unified; the back-end
 * mirrors that. Validation lives in BulkRequest; per-action work goes
 * through ItemWriter so the existing search-reindex + activity-log paths
 * fire normally.
 *
 * `move` returns the previous-parent map in the flash so the Vue side can
 * surface an Undo toast that POSTs a reverse move.
 */
class BulkController extends Controller
{
    public function __construct(private readonly ItemWriter $writer) {}

    public function __invoke(BulkRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');
        $action = (string) $request->validated('action');

        // Eager-load `images` for the delete path — the model's deleting
        // hook iterates each image's variants on disk, which would lazy-load
        // and trip Eloquent strict-mode under tests.
        $items = Item::query()
            ->whereIn('id', $ids)
            ->when($action === 'delete', fn ($q) => $q->with('images'))
            ->get();

        return match ($action) {
            'delete' => $this->delete($items),
            'move' => $this->move($items, $request->integer('parent_id') ?: null),
            'attach-tag' => $this->attachTag($items, (int) $request->validated('tag_id')),
            'detach-tag' => $this->detachTag($items, (int) $request->validated('tag_id')),
            // BulkRequest's enum rule guarantees we never get here, but the
            // match arm is required to make the return type non-mixed.
            default => back(),
        };
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    private function delete($items): RedirectResponse
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                $this->writer->delete($item);
            }
        });

        return back()->with('bulk_result', [
            'action' => 'delete',
            'count' => $items->count(),
        ]);
    }

    /**
     * Move every selected item to $parentId (or null for top level).
     * Captures the previous parent_id per item so the UI can render an
     * Undo toast that reverses the move.
     *
     * @param  Collection<int, Item>  $items
     */
    private function move($items, ?int $parentId): RedirectResponse
    {
        $previous = $items->mapWithKeys(fn (Item $i) => [$i->id => $i->parent_id])->all();

        // Suppress Scout's per-save auto-index inside the loop. Each
        // ItemWriter::move() also calls reindexDescendants(), which would
        // otherwise produce O(items × descendants) Meilisearch round-trips
        // — slow on its own, painfully slow with semantic embeddings
        // turned on. Per-item Eloquent events (saving/saved + activity-log
        // entries) still fire normally; only the search index is held back.
        //
        // After the moves commit, push every affected id (movers + their
        // subtrees) through `searchable()` once, so Scout sends a single
        // bulk PUT to Meilisearch.
        DB::transaction(function () use ($items, $parentId): void {
            Item::withoutSyncingToSearch(function () use ($items, $parentId): void {
                foreach ($items as $item) {
                    $this->writer->move($item, $parentId);
                }
            });
        });

        $this->reindexAfter($items);

        return back()->with('bulk_result', [
            'action' => 'move',
            'count' => $items->count(),
            'parent_id' => $parentId,
            'previous' => $previous,
        ]);
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    private function attachTag($items, int $tagId): RedirectResponse
    {
        DB::transaction(function () use ($items, $tagId): void {
            Item::withoutSyncingToSearch(function () use ($items, $tagId): void {
                foreach ($items as $item) {
                    // attach() is the right primitive here: idempotent enough
                    // when paired with the unique pivot, and doesn't replace
                    // an item's existing tags the way sync() would.
                    $item->tags()->syncWithoutDetaching([$tagId]);
                }
            });
        });

        $this->reindexAfter($items, includeDescendants: false);

        return back()->with('bulk_result', [
            'action' => 'attach-tag',
            'count' => $items->count(),
            'tag_id' => $tagId,
        ]);
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    private function detachTag($items, int $tagId): RedirectResponse
    {
        DB::transaction(function () use ($items, $tagId): void {
            Item::withoutSyncingToSearch(function () use ($items, $tagId): void {
                foreach ($items as $item) {
                    $item->tags()->detach($tagId);
                }
            });
        });

        $this->reindexAfter($items, includeDescendants: false);

        return back()->with('bulk_result', [
            'action' => 'detach-tag',
            'count' => $items->count(),
            'tag_id' => $tagId,
        ]);
    }

    /**
     * Dispatch a background re-index for every item the operation touched,
     * plus (optionally) the descendants of moved items whose `location_path`
     * depends on the moved ancestor's name + position.
     *
     * Queued rather than inline because `Item::toSearchableArray()` calls
     * the Ollama embedder synchronously per item. A bulk move of N items
     * with M descendants becomes N+M embedding round-trips on the request
     * thread — easily 10+ seconds with semantic search on. The DB write
     * is already done by the time we get here, the browse UI reads
     * directly from Eloquent, so only the search box has a brief stale
     * window while the worker catches up.
     *
     * @param  Collection<int, Item>  $items
     */
    private function reindexAfter(Collection $items, bool $includeDescendants = true): void
    {
        $ids = $items->pluck('id')->all();

        if ($includeDescendants) {
            foreach ($items as $item) {
                $ids = [...$ids, ...$item->descendantIds()];
            }
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        ReindexItemsJob::dispatch($ids);
    }
}
