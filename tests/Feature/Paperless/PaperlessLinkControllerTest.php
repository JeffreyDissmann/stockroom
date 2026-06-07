<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\PaperlessLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('deletes the local pivot row and does not call Paperless', function () {
    Http::preventStrayRequests();
    Http::fake();

    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect();

    expect(PaperlessLink::query()->count())->toBe(0);

    // The whole point of the URL-backlink redesign: unlinking is local
    // only, no round-trip to Paperless.
    Http::assertNothingSent();
});

it('only removes the targeted link, leaving siblings intact', function () {
    $item = Item::factory()->create();
    $item->paperlessLinks()->createMany([
        ['paperless_document_id' => 547],
        ['paperless_document_id' => 999],
    ]);

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect();

    expect(PaperlessLink::query()->pluck('paperless_document_id')->all())->toBe([999]);
});

it('redirects guests to login', function () {
    auth()->logout();
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect('/login');
});

/**
 * HTTP fixture for the manual-link flow: the verification GET, the tag and
 * custom-field lookups behind annotateProcessed, and the annotation PATCH.
 * Doc 404 exists; anything else 404s — mirrors relinkFakes' router shape.
 */
function manualPaperlessLinkFakes(bool $patchFails = false): callable
{
    return function ($request) use ($patchFails) {
        $url = $request->url();
        $method = $request->method();

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
            if ($method === 'GET') {
                return Http::response(['id' => 447, 'title' => 'Washing machine receipt', 'tags' => [], 'custom_fields' => []]);
            }
            if ($method === 'PATCH') {
                return Http::response([], $patchFails ? 500 : 200);
            }
        }

        return Http::response([], 404);
    };
}

describe('store', function () {
    beforeEach(function () {
        config()->set('paperless.url', 'https://paperless.test');
        config()->set('paperless.token', 'TOKEN');
        config()->set('paperless.trigger_tag', 'Add to Stockroom');
        config()->set('paperless.linked_tag', 'Stockroom');
        config()->set('paperless.link_custom_field', 'Stockroom URL');
        config()->set('app.url', 'https://stockroom.test');
    });

    it('links a document referenced by bare id and annotates the Paperless side', function () {
        Http::fake(manualPaperlessLinkFakes());
        $item = Item::factory()->create();

        $this->post("/items/{$item->id}/paperless-links", ['document' => '447'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($item->paperlessLinks()->pluck('paperless_document_id')->all())->toBe([447]);

        // The same single-PATCH annotation as intake: linked tag + backlink.
        Http::assertSent(fn ($r) => $r->method() === 'PATCH'
            && str_contains($r->url(), '/api/documents/447/')
            && in_array(10, $r['tags'], true)
            && str_contains(json_encode($r['custom_fields']), 'paperless_document=447'));
    });

    it('snapshots the document metadata onto the link row', function () {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/api/document_types/7/')) {
                return Http::response(['id' => 7, 'name' => 'Rechnung']);
            }
            if (str_contains($url, '/api/correspondents/233/')) {
                return Http::response(['id' => 233, 'name' => 'MediaMarkt']);
            }
            if (str_contains($url, '/api/tags/') || str_contains($url, '/api/custom_fields/')) {
                return Http::response(['results' => [['id' => 9, 'name' => 'x']]]);
            }
            if (preg_match('#/api/documents/447/$#', $url)) {
                return $request->method() === 'PATCH'
                    ? Http::response([], 200)
                    : Http::response([
                        'id' => 447,
                        'title' => 'Rechnung AEG Waschmaschine',
                        'document_type' => 7,
                        'correspondent' => 233,
                        'tags' => [],
                        'custom_fields' => [],
                    ]);
            }

            return Http::response([], 404);
        });

        $item = Item::factory()->create();

        $this->post("/items/{$item->id}/paperless-links", ['document' => '447'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $link = PaperlessLink::sole();
        expect($link->document_title)->toBe('Rechnung AEG Waschmaschine')
            ->and($link->document_type)->toBe('Rechnung')
            ->and($link->correspondent)->toBe('MediaMarkt');
    });

    it('links a document referenced by pasted URL', function () {
        Http::fake(manualPaperlessLinkFakes());
        $item = Item::factory()->create();

        $this->post("/items/{$item->id}/paperless-links", ['document' => 'https://paperless.test/documents/447/'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($item->paperlessLinks()->pluck('paperless_document_id')->all())->toBe([447]);
    });

    it('is idempotent for an already-linked document', function () {
        Http::fake(manualPaperlessLinkFakes());
        $item = Item::factory()->create();
        $item->paperlessLinks()->create(['paperless_document_id' => 447]);

        $this->post("/items/{$item->id}/paperless-links", ['document' => '447'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($item->paperlessLinks()->count())->toBe(1);
    });

    it('rejects a document Paperless does not know', function () {
        Http::fake(manualPaperlessLinkFakes());
        $item = Item::factory()->create();

        $this->from("/items/{$item->id}/edit")
            ->post("/items/{$item->id}/paperless-links", ['document' => '999'])
            ->assertRedirect("/items/{$item->id}/edit")
            ->assertSessionHasErrors('document');

        expect($item->paperlessLinks()->count())->toBe(0);
    });

    it('rejects unparseable input without calling Paperless', function () {
        Http::preventStrayRequests();
        Http::fake();
        $item = Item::factory()->create();

        $this->post("/items/{$item->id}/paperless-links", ['document' => 'not a reference'])
            ->assertRedirect()
            ->assertSessionHasErrors('document');

        expect($item->paperlessLinks()->count())->toBe(0);
        Http::assertNothingSent();
    });

    it('keeps the link when the annotation PATCH fails', function () {
        Http::fake(manualPaperlessLinkFakes(patchFails: true));
        $item = Item::factory()->create();

        // Best-effort annotation: the local link is already committed and
        // the repair job re-applies the Paperless side later.
        $this->post("/items/{$item->id}/paperless-links", ['document' => '447'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($item->paperlessLinks()->pluck('paperless_document_id')->all())->toBe([447]);
    });

    it('404s when the Paperless integration is disabled', function () {
        config()->set('paperless.url', '');
        $item = Item::factory()->create();

        $this->post("/items/{$item->id}/paperless-links", ['document' => '447'])
            ->assertNotFound();
    });
});

describe('search', function () {
    beforeEach(function () {
        config()->set('paperless.url', 'https://paperless.test');
        config()->set('paperless.token', 'TOKEN');

        $this->actingAs(User::factory()->admin()->create());
    });

    it('returns id/title pairs for the query', function () {
        Http::fake([
            'https://paperless.test/api/documents/*' => Http::response([
                'results' => [['id' => 447, 'title' => 'Washing machine receipt']],
            ]),
        ]);

        $this->getJson('/paperless/documents?q=washing')
            ->assertOk()
            ->assertExactJson(['documents' => [['id' => 447, 'title' => 'Washing machine receipt']]]);
    });

    it('is admin-only', function () {
        // Paperless per-user permissions can't be mirrored — free search
        // over the service token is reserved for household admins.
        $this->actingAs(User::factory()->create());

        $this->getJson('/paperless/documents?q=washing')->assertForbidden();
    });

    it('404s when the Paperless integration is disabled', function () {
        config()->set('paperless.url', '');

        $this->getJson('/paperless/documents?q=washing')->assertNotFound();
    });

    it('502s when Paperless is unreachable', function () {
        Http::fake([
            'https://paperless.test/api/documents/*' => Http::response([], 500),
        ]);

        $this->getJson('/paperless/documents?q=washing')->assertStatus(502);
    });
});
