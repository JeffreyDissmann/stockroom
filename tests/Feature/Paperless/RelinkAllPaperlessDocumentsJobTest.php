<?php

declare(strict_types=1);

use App\Jobs\RelinkAllPaperlessDocumentsJob;
use App\Models\Item;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('paperless.trigger_tag', 'Add to Stockroom');
    config()->set('paperless.linked_tag', 'Stockroom');
    config()->set('paperless.link_custom_field', 'Stockroom URL');
    config()->set('app.url', 'https://stockroom.test');
});

/**
 * Per-doc HTTP fixture: tag + custom_field lookups, the doc GET, and the
 * PATCH succeeds. Mirrors the shape in ProcessPaperlessDocumentJobTest but
 * scoped to a single id rather than a router-style switch.
 */
function relinkFakes(): callable
{
    return function ($request) {
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
        if (preg_match('#/api/documents/(\d+)/$#', $url, $m)) {
            if ($method === 'GET') {
                return Http::response(['id' => (int) $m[1], 'tags' => [10], 'custom_fields' => []]);
            }
            if ($method === 'PATCH') {
                return Http::response([], 200);
            }
        }

        return Http::response([], 404);
    };
}

it('PATCHes every distinct linked Paperless document with the canonical annotation', function () {
    Http::fake(relinkFakes());

    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 100]);
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 100]); // duplicate doc id
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 200]);

    (new RelinkAllPaperlessDocumentsJob)->handle(app(PaperlessClient::class));

    // One PATCH per distinct doc id — not three (the two links pointing at
    // doc 100 must collapse to a single PATCH).
    $patches = collect(Http::recorded())->filter(fn ($p) => $p[0]->method() === 'PATCH');
    expect($patches->count())->toBe(2);

    // PATCH carries the current backlink URL for each respective doc.
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/100/')
        && collect($r['custom_fields'])->contains(fn ($e) => (int) $e['field'] === 5
            && (string) $e['value'] === 'https://stockroom.test/search?paperless_document=100'));
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/200/')
        && collect($r['custom_fields'])->contains(fn ($e) => (int) $e['field'] === 5
            && (string) $e['value'] === 'https://stockroom.test/search?paperless_document=200'));
});

it('keeps going when a single document re-link fails', function () {
    // Custom-field lookup always succeeds; doc 100 PATCH errors out, doc 200
    // succeeds. The job should log the 100 failure and still PATCH 200.
    Http::fake(function ($request) {
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
        if (str_contains($url, '/api/documents/100/')) {
            return Http::response([], 500);
        }
        if (preg_match('#/api/documents/200/$#', $url)) {
            return $method === 'GET'
                ? Http::response(['id' => 200, 'tags' => [], 'custom_fields' => []])
                : Http::response([], 200);
        }

        return Http::response([], 404);
    });

    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 100]);
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 200]);

    Log::spy();

    (new RelinkAllPaperlessDocumentsJob)->handle(app(PaperlessClient::class));

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => $msg === 'paperless.relink.doc_failed')
        ->once();

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/200/'));
});

it('does nothing when no links exist', function () {
    Http::preventStrayRequests();
    Http::fake();

    (new RelinkAllPaperlessDocumentsJob)->handle(app(PaperlessClient::class));

    Http::assertNothingSent();
});

it('writes a running → done status into the cache as it progresses', function () {
    Http::fake(relinkFakes());

    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 100]);
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 200]);

    Cache::forget(RelinkAllPaperlessDocumentsJob::STATUS_KEY);

    (new RelinkAllPaperlessDocumentsJob)->handle(app(PaperlessClient::class));

    expect(Cache::get(RelinkAllPaperlessDocumentsJob::STATUS_KEY))
        ->toMatchArray(['state' => 'done', 'done' => 2, 'failed' => 0, 'total' => 2]);
});
