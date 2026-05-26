<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Search\ItemEmbedder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class SemanticSearchTest extends TestCase
{
    use RefreshDatabase;

    private function enableSemantic(): void
    {
        config([
            'scout.driver' => 'meilisearch',
            'scout.meilisearch.hybrid.embedder' => 'items',
        ]);
    }

    public function test_embedder_is_disabled_without_meilisearch_and_makes_no_calls(): void
    {
        Embeddings::fake(); // default test driver is "collection" → semantic off

        $this->assertNull(app(ItemEmbedder::class)->embed('a cordless drill'));

        Embeddings::assertNothingGenerated();
    }

    public function test_embedder_returns_a_vector_when_enabled(): void
    {
        $this->enableSemantic();
        Embeddings::fake([[[0.1, 0.2, 0.3]]]);

        $this->assertSame([0.1, 0.2, 0.3], app(ItemEmbedder::class)->embed('a cordless drill'));
    }

    public function test_embedder_returns_null_for_blank_text(): void
    {
        $this->enableSemantic();
        Embeddings::fake();

        $this->assertNull(app(ItemEmbedder::class)->embed('   '));

        Embeddings::assertNothingGenerated();
    }

    public function test_searchable_array_attaches_the_vector_when_enabled(): void
    {
        $this->enableSemantic();
        Embeddings::fake([[[0.4, 0.5, 0.6]]]);

        $item = Item::withoutSyncingToSearch(
            fn () => Item::factory()->create(['name' => 'DeWalt Drill']),
        );

        $this->assertSame(['items' => [0.4, 0.5, 0.6]], $item->toSearchableArray()['_vectors'] ?? null);
    }

    public function test_searchable_array_has_no_vector_when_disabled(): void
    {
        Embeddings::fake();

        $item = Item::withoutSyncingToSearch(
            fn () => Item::factory()->create(['name' => 'DeWalt Drill']),
        );

        $this->assertArrayNotHasKey('_vectors', $item->toSearchableArray());
        Embeddings::assertNothingGenerated();
    }
}
