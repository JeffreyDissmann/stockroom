<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Search\ItemEmbedder;
use Closure;
use Laravel\Scout\Builder;
use Throwable;

/**
 * Shared hybrid (keyword + semantic) item search used by the search UI and the
 * AI assistant's search tool. Applies Meilisearch hybrid options when semantic
 * search is configured and falls back to keyword search otherwise.
 */
class InventorySearch
{
    public function __construct(private readonly ItemEmbedder $embedder) {}

    /**
     * Run a Scout search and finalise it with $execute (->get(), ->keys()…).
     *
     * @template TReturn
     *
     * @param  callable(Builder): TReturn  $execute
     * @return TReturn
     */
    public function search(string $query, callable $execute): mixed
    {
        if (($hybrid = $this->hybrid($query)) !== null) {
            try {
                return $execute(Item::search($query, $hybrid));
            } catch (Throwable $e) {
                report($e); // keep visibility, then degrade to keyword search
            }
        }

        return $execute(Item::search($query));
    }

    /**
     * Meilisearch hybrid-search options, or null when semantic search is off,
     * the driver isn't Meilisearch, or the query can't be embedded app-side.
     */
    private function hybrid(string $query): ?Closure
    {
        $embedder = config('scout.meilisearch.hybrid.embedder');

        if (config('scout.driver') !== 'meilisearch' || ! $embedder) {
            return null;
        }

        $vector = $this->embedder->embed($query);

        if ($vector === null) {
            return null;
        }

        $ratio = (float) config('scout.meilisearch.hybrid.semantic_ratio', 0.5);

        return fn ($index, string $q, array $options) => $index->search($q, $options + [
            'vector' => $vector,
            'hybrid' => ['embedder' => $embedder, 'semanticRatio' => $ratio],
        ]);
    }
}
