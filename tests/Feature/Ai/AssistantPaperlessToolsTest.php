<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\InventoryAssistant;
use App\Ai\Tools\LinkPaperlessDocument;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AssistantPaperlessToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('paperless.url', 'https://paperless.test');
        config()->set('paperless.token', 'TOKEN');
        config()->set('paperless.trigger_tag', 'Add to Stockroom');
        config()->set('paperless.linked_tag', 'Stockroom');
        config()->set('paperless.link_custom_field', 'Stockroom URL');
        config()->set('app.url', 'https://stockroom.test');
    }

    /**
     * The verification GET, the lookups behind annotateProcessed, and the
     * annotation PATCH — document 447 exists, everything else 404s.
     */
    private function fakePaperless(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/api/tags/') && str_contains($url, 'Add%20to%20Stockroom')) {
                return Http::response(['results' => [['id' => 9, 'name' => 'Add to Stockroom']]]);
            }
            if (str_contains($url, '/api/tags/') && str_contains($url, 'Stockroom')) {
                return Http::response(['results' => [['id' => 10, 'name' => 'Stockroom']]]);
            }
            if (str_contains($url, '/api/custom_fields/')) {
                return Http::response(['results' => [['id' => 5, 'name' => 'Stockroom URL']]]);
            }
            if (preg_match('#/api/documents/447/$#', $url)) {
                return $request->method() === 'PATCH'
                    ? Http::response([], 200)
                    : Http::response(['id' => 447, 'title' => 'Washing machine receipt', 'tags' => [], 'custom_fields' => []]);
            }

            return Http::response([], 404);
        });
    }

    public function test_link_paperless_document_links_by_id_and_reports_the_title_to_admins(): void
    {
        $this->fakePaperless();
        $this->actingAs(User::factory()->admin()->create());
        $item = Item::factory()->create(['name' => 'Washing Machine']);

        $result = app(LinkPaperlessDocument::class)->handle(new Request([
            'item_id' => $item->id,
            'document' => '447',
        ]));

        $this->assertSame([447], $item->paperlessLinks()->pluck('paperless_document_id')->all());
        $this->assertStringContainsString('Washing machine receipt', $result);
        $this->assertStringContainsString("[Washing Machine](/items/{$item->id})", $result);
    }

    public function test_link_paperless_document_confirms_only_existence_to_members(): void
    {
        $this->fakePaperless();
        $this->actingAs(User::factory()->create());
        $item = Item::factory()->create();

        // The link still happens — members may link docs they explicitly
        // reference — but describing content by id would be slow-motion
        // browsing, which (like search) is admin-only.
        $result = app(LinkPaperlessDocument::class)->handle(new Request([
            'item_id' => $item->id,
            'document' => '447',
        ]));

        $this->assertSame([447], $item->paperlessLinks()->pluck('paperless_document_id')->all());
        $this->assertStringContainsString('Linked Paperless document #447', $result);
        $this->assertStringNotContainsString('Washing machine receipt', $result);
    }

    public function test_link_paperless_document_accepts_a_pasted_url(): void
    {
        $this->fakePaperless();
        $item = Item::factory()->create();

        app(LinkPaperlessDocument::class)->handle(new Request([
            'item_id' => $item->id,
            'document' => 'https://paperless.test/documents/447/',
        ]));

        $this->assertSame([447], $item->paperlessLinks()->pluck('paperless_document_id')->all());
    }

    public function test_link_paperless_document_rejects_invalid_input_without_writing(): void
    {
        $this->fakePaperless();
        $item = Item::factory()->create();
        $tool = app(LinkPaperlessDocument::class);

        $this->assertStringContainsString('No item found', $tool->handle(new Request(['item_id' => 999999, 'document' => '447'])));
        $this->assertStringContainsString('numeric id', $tool->handle(new Request(['item_id' => $item->id, 'document' => 'the receipt'])));
        // Unknown in Paperless: verified before linking, so nothing is written.
        $this->assertStringContainsString('could not be verified', $tool->handle(new Request(['item_id' => $item->id, 'document' => '999'])));

        $this->assertSame(0, $item->paperlessLinks()->count());
    }

    public function test_the_tool_is_only_registered_while_paperless_is_configured(): void
    {
        $toolClasses = fn (): array => array_map(
            static fn (object $tool): string => $tool::class,
            iterator_to_array((new InventoryAssistant)->tools()),
        );

        $this->assertContains(LinkPaperlessDocument::class, $toolClasses());
        $this->assertStringContainsString('link_paperless_document', (new InventoryAssistant)->instructions());

        // Mirrors the UI: every Paperless surface disappears when disabled.
        config()->set('paperless.url', '');

        $this->assertNotContains(LinkPaperlessDocument::class, $toolClasses());
        $this->assertStringNotContainsString('link_paperless_document', (new InventoryAssistant)->instructions());
    }
}
