<?php

declare(strict_types=1);

use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
});

it('returns null from fromConfig when the integration is disabled', function () {
    config()->set('paperless.url', '');
    expect(PaperlessClient::fromConfig())->toBeNull();
});

it('returns null from fromConfig when the token is empty', function () {
    config()->set('paperless.token', '');
    expect(PaperlessClient::fromConfig())->toBeNull();
});

it('builds a working client from fromConfig with the configured base url + token', function () {
    Http::fake([
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'content' => 'OCR text']),
    ]);

    $client = PaperlessClient::fromConfig();
    expect($client)->not->toBeNull();

    $doc = $client->document(42);
    expect($doc['content'])->toBe('OCR text');

    // Asserts the bearer carries the Paperless-style 'Token …' prefix
    // (not 'Bearer …'). Paperless rejects the latter.
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Token TOKEN'));
});

it('document() throws PaperlessException on 404', function () {
    Http::fake([
        'https://paperless.test/api/documents/999/' => Http::response(['detail' => 'Not found'], 404),
    ]);

    expect(fn () => PaperlessClient::fromConfig()->document(999))
        ->toThrow(PaperlessException::class, '999 not found');
});

it('document() throws on 401 with a credentials-rejected message', function () {
    Http::fake([
        'https://paperless.test/api/documents/1/' => Http::response([], 401),
    ]);

    expect(fn () => PaperlessClient::fromConfig()->document(1))
        ->toThrow(PaperlessException::class, 'rejected the API token');
});

it('addTag() resolves the tag name and PATCHes the document with the merged tag id', function () {
    Http::fake([
        'https://paperless.test/api/tags/?name__iexact=Stockroom' => Http::response([
            'results' => [['id' => 7, 'name' => 'Stockroom']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'tags' => [3, 4]]),
    ]);

    PaperlessClient::fromConfig()->addTag(42, 'Stockroom');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/42/')
        && $r['tags'] === [3, 4, 7]);
});

it('addTag() throws when the tag does not exist in Paperless', function () {
    Http::fake([
        'https://paperless.test/api/tags/*' => Http::response(['results' => []]),
    ]);

    expect(fn () => PaperlessClient::fromConfig()->addTag(42, 'never-created'))
        ->toThrow(PaperlessException::class, "tag 'never-created' not found");
});

it('removeTag() PATCHes the document with the tag id detached', function () {
    Http::fake([
        'https://paperless.test/api/tags/?name__iexact=Add%20to%20Stockroom' => Http::response([
            'results' => [['id' => 9, 'name' => 'Add to Stockroom']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'tags' => [3, 9, 4]]),
    ]);

    PaperlessClient::fromConfig()->removeTag(42, 'Add to Stockroom');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['tags'] === [3, 4]);
});

it('setCustomField() writes the value into the named field, preserving other fields', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [
                ['id' => 1, 'name' => 'invoice_number'],
                ['id' => 2, 'name' => 'Stockroom URL'],
            ],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'custom_fields' => [
            ['field' => 1, 'value' => 'INV-001'],
        ]]),
    ]);

    PaperlessClient::fromConfig()->setCustomField(42, 'Stockroom URL', '17,42');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['custom_fields'] === [
        ['field' => 1, 'value' => 'INV-001'],
        ['field' => 2, 'value' => '17,42'],
    ]);
});

it('setCustomField() replaces the value when the field is already populated', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'Stockroom URL']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'custom_fields' => [
            ['field' => 2, 'value' => 'old'],
        ]]),
    ]);

    PaperlessClient::fromConfig()->setCustomField(42, 'Stockroom URL', 'new');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && $r['custom_fields'] === [['field' => 2, 'value' => 'new']]);
});

it('caches tag and custom_field id lookups per client instance', function () {
    Http::fake([
        'https://paperless.test/api/tags/?name__iexact=Add%20to%20Stockroom' => Http::response([
            'results' => [['id' => 9, 'name' => 'Add to Stockroom']],
        ]),
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'Stockroom URL']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response([
            'tags' => [9],
            'custom_fields' => [],
        ]),
    ]);

    $client = PaperlessClient::fromConfig();
    // First call warms the caches; subsequent calls should hit them.
    $client->addTag(42, 'Add to Stockroom');
    $client->removeTag(42, 'Add to Stockroom');
    $client->setCustomField(42, 'Stockroom URL', 'x');
    $client->getCustomField(42, 'Stockroom URL');

    // Tag iexact lookup: exactly one GET regardless of how many tag ops follow.
    $tagLookups = collect(Http::recorded())
        ->filter(fn ($p) => $p[0]->method() === 'GET' && str_contains($p[0]->url(), 'name__iexact=Add%20to%20Stockroom'));
    expect($tagLookups->count())->toBe(1);

    // Custom field listing: exactly one GET (Paperless has no name filter on
    // /api/custom_fields/, so we GET the whole list once and memoize).
    $fieldLookups = collect(Http::recorded())
        ->filter(fn ($p) => $p[0]->method() === 'GET' && str_ends_with($p[0]->url(), '/api/custom_fields/'));
    expect($fieldLookups->count())->toBe(1);
});

it('annotateProcessed() produces a single PATCH with merged tags and custom_fields', function () {
    Http::fake([
        'https://paperless.test/api/tags/?name__iexact=Add%20to%20Stockroom' => Http::response([
            'results' => [['id' => 9, 'name' => 'Add to Stockroom']],
        ]),
        'https://paperless.test/api/tags/?name__iexact=Stockroom' => Http::response([
            'results' => [['id' => 10, 'name' => 'Stockroom']],
        ]),
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'Stockroom URL']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response([
            'tags' => [3, 9, 4],
            'custom_fields' => [['field' => 1, 'value' => 'INV-001']],
        ]),
    ]);

    PaperlessClient::fromConfig()->annotateProcessed(42, 'Add to Stockroom', 'Stockroom', 'Stockroom URL', 'https://stockroom.test/search?paperless_document=42');

    $patches = collect(Http::recorded())->filter(fn ($pair) => $pair[0]->method() === 'PATCH');

    expect($patches->count())->toBe(1);

    $patch = $patches->first()[0];
    expect($patch['tags'])->toBe([3, 4, 10]) // trigger 9 dropped, linked 10 appended
        ->and($patch['custom_fields'])->toBe([
            ['field' => 1, 'value' => 'INV-001'],
            ['field' => 2, 'value' => 'https://stockroom.test/search?paperless_document=42'],
        ]);
});

it('getCustomField() returns the value when populated', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'Stockroom URL']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['custom_fields' => [
            ['field' => 2, 'value' => '17,42'],
        ]]),
    ]);

    expect(PaperlessClient::fromConfig()->getCustomField(42, 'Stockroom URL'))
        ->toBe('17,42');
});

it('getCustomField() returns null when the field exists but is empty on this doc', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'Stockroom URL']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['custom_fields' => []]),
    ]);

    expect(PaperlessClient::fromConfig()->getCustomField(42, 'Stockroom URL'))->toBeNull();
});

it('searchDocuments() passes the query through and trims results to id/title pairs', function () {
    Http::fake([
        'https://paperless.test/api/documents/*' => Http::response([
            'results' => [
                ['id' => 447, 'title' => 'Washing machine receipt', 'content' => 'huge OCR blob'],
                ['id' => 12, 'title' => 'Dryer manual', 'tags' => [1, 2]],
            ],
        ]),
    ]);

    $results = PaperlessClient::fromConfig()->searchDocuments('washing');

    expect($results)->toBe([
        ['id' => 447, 'title' => 'Washing machine receipt'],
        ['id' => 12, 'title' => 'Dryer manual'],
    ]);

    Http::assertSent(fn ($r) => str_contains($r->url(), 'query=washing')
        && str_contains($r->url(), 'page_size=10'));
});

it('searchDocuments() lists the most recent documents for an empty query', function () {
    Http::fake([
        'https://paperless.test/api/documents/*' => Http::response(['results' => []]),
    ]);

    PaperlessClient::fromConfig()->searchDocuments('  ');

    // No full-text query — the picker shows the newest docs instead.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'ordering=-created')
        && ! str_contains($r->url(), 'query='));
});

it('searchDocuments() throws PaperlessException on an API error', function () {
    Http::fake([
        'https://paperless.test/api/documents/*' => Http::response([], 500),
    ]);

    expect(fn () => PaperlessClient::fromConfig()->searchDocuments('x'))
        ->toThrow(PaperlessException::class);
});
