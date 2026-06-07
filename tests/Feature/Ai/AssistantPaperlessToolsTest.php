<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\InventoryAssistant;
use App\Ai\Tools\GetPaperlessDocument;
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

    public function test_get_paperless_document_returns_capped_content_for_linked_documents(): void
    {
        Http::fake([
            'https://paperless.test/api/documents/447/' => Http::response([
                'id' => 447,
                'title' => 'Washing machine receipt',
                'content' => 'AEG L7WE17680 ... 2 Jahre Garantie ...',
            ]),
        ]);

        $item = Item::factory()->create(['name' => 'Washing Machine']);
        $item->paperlessLinks()->create(['paperless_document_id' => 447]);

        $result = app(GetPaperlessDocument::class)->handle(new Request(['document_id' => 447]));

        $this->assertStringContainsString('Washing machine receipt', $result);
        $this->assertStringContainsString("Linked to: [Washing Machine](/items/{$item->id})", $result);
        $this->assertStringContainsString('2 Jahre Garantie', $result);
    }

    public function test_get_paperless_document_truncates_very_long_content(): void
    {
        Http::fake([
            'https://paperless.test/api/documents/447/' => Http::response([
                'id' => 447,
                'title' => 'Manual',
                'content' => str_repeat('a', 12_000),
            ]),
        ]);

        Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 447]);

        $result = app(GetPaperlessDocument::class)->handle(new Request(['document_id' => 447]));

        $this->assertStringContainsString('Truncated', $result);
        // Capped content + framing lines stay well under the raw length.
        $this->assertLessThan(11_000, strlen($result));
    }

    public function test_get_paperless_document_refuses_unlinked_documents_without_calling_paperless(): void
    {
        Http::fake();

        // The doc may well exist in Paperless — but discovering unlinked
        // content by id is browsing, which stays admin-only via the UI
        // search. Unlinked reads exactly like nonexistent.
        $result = app(GetPaperlessDocument::class)->handle(new Request(['document_id' => 999]));

        $this->assertStringContainsString('No linked document with that id', $result);
        Http::assertNothingSent();
    }

    public function test_the_tools_are_only_registered_while_paperless_is_configured(): void
    {
        $toolClasses = fn (): array => array_map(
            static fn (object $tool): string => $tool::class,
            iterator_to_array((new InventoryAssistant)->tools()),
        );

        $this->assertContains(LinkPaperlessDocument::class, $toolClasses());
        $this->assertContains(GetPaperlessDocument::class, $toolClasses());
        $this->assertStringContainsString('link_paperless_document', (new InventoryAssistant)->instructions());
        $this->assertStringContainsString('get_paperless_document', (new InventoryAssistant)->instructions());

        // Mirrors the UI: every Paperless surface disappears when disabled.
        config()->set('paperless.url', '');

        $this->assertNotContains(LinkPaperlessDocument::class, $toolClasses());
        $this->assertNotContains(GetPaperlessDocument::class, $toolClasses());
        $this->assertStringNotContainsString('link_paperless_document', (new InventoryAssistant)->instructions());
        $this->assertStringNotContainsString('get_paperless_document', (new InventoryAssistant)->instructions());
    }
}
