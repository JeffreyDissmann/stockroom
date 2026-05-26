<?php

declare(strict_types=1);

namespace App\Search;

use Laravel\Ai\Embeddings;
use Throwable;

/**
 * Turns text into an embedding vector for semantic search, using the Laravel AI
 * SDK (provider-agnostic via config('ai.default_for_embeddings')). Both indexing
 * (document vectors) and searching (query vector) go through here so Meilisearch
 * only ever stores/ranks "userProvided" vectors — it never calls an embedder.
 *
 * Embedding is best-effort: any failure returns null so indexing/search degrade
 * to keyword behaviour rather than breaking.
 */
class ItemEmbedder
{
    /**
     * Whether semantic search is active (Meilisearch driver + an embedder name).
     */
    public function enabled(): bool
    {
        return config('scout.driver') === 'meilisearch'
            && filled(config('scout.meilisearch.hybrid.embedder'));
    }

    /**
     * Embed a piece of text, or null when semantic search is off, the text is
     * empty, or the embedding provider is unavailable.
     *
     * @return list<float>|null
     */
    public function embed(string $text): ?array
    {
        $text = trim($text);

        if ($text === '' || ! $this->enabled()) {
            return null;
        }

        try {
            return Embeddings::for([$text])
                ->cache()
                ->generate(
                    provider: config('ai.default_for_embeddings'),
                    model: config('ai.embeddings_model'),
                )
                ->first();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
