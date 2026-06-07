<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Jobs\RebuildSearchIndexJob;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\MeilisearchEngine;
use Mockery;
use Tests\TestCase;

class SearchIndexReindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_authentication(): void
    {
        $this->get('/household/search-index')->assertRedirect('/login');
    }

    public function test_index_page_renders_with_counts(): void
    {
        Item::withoutSyncingToSearch(fn () => Item::factory()->count(3)->create());

        $this->actingAs(User::factory()->admin()->create())
            ->get('/household/search-index')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('household/SearchIndex')
                ->where('total', 3)
                ->has('semantic'));
    }

    public function test_rebuild_dispatches_the_job_and_marks_running(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/search-index')
            ->assertRedirect();

        Queue::assertPushed(RebuildSearchIndexJob::class);
        $this->assertSame('running', Cache::get(RebuildSearchIndexJob::STATUS_KEY)['state'] ?? null);
    }

    public function test_job_indexes_all_items_and_reports_done(): void
    {
        Item::withoutSyncingToSearch(fn () => Item::factory()->count(5)->create());

        (new RebuildSearchIndexJob)->handle();

        $status = Cache::get(RebuildSearchIndexJob::STATUS_KEY);

        $this->assertSame('done', $status['state']);
        $this->assertSame(5, $status['total']);
        $this->assertSame(5, $status['indexed']);
    }

    public function test_job_flushes_and_resyncs_settings_before_reimporting(): void
    {
        config(['scout.driver' => 'meilisearch']);

        Item::withoutSyncingToSearch(fn () => Item::factory()->count(2)->create());

        $engine = Mockery::spy(MeilisearchEngine::class);
        $manager = Mockery::mock(EngineManager::class);
        $manager->shouldReceive('engine')->andReturn($engine);
        $this->swap(EngineManager::class, $manager);

        (new RebuildSearchIndexJob)->handle();

        // Meilisearch refuses to register a userProvided embedder while the
        // index holds vector-less documents, so the rebuild must flush and
        // re-push the index settings before re-importing.
        $engine->shouldHaveReceived('flush')->once();
        $engine->shouldHaveReceived('updateIndexSettings')->once();
        $engine->shouldHaveReceived('update');

        $this->assertSame('done', Cache::get(RebuildSearchIndexJob::STATUS_KEY)['state'] ?? null);
    }

    public function test_job_indexes_inline_even_when_scout_queueing_is_enabled(): void
    {
        config(['scout.queue' => true]);

        Item::withoutSyncingToSearch(fn () => Item::factory()->count(3)->create());

        Queue::fake();

        (new RebuildSearchIndexJob)->handle();

        // The rebuild must not fan out MakeSearchable jobs — it indexes
        // inline so the reported progress reflects work actually done.
        Queue::assertNothingPushed();

        $status = Cache::get(RebuildSearchIndexJob::STATUS_KEY);

        $this->assertSame('done', $status['state']);
        $this->assertSame(3, $status['indexed']);
    }
}
