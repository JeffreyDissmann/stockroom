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
        'https://paperless.test/api/tags/?name__iexact=stockbox' => Http::response([
            'results' => [['id' => 7, 'name' => 'stockbox']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'tags' => [3, 4]]),
    ]);

    PaperlessClient::fromConfig()->addTag(42, 'stockbox');

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
        'https://paperless.test/api/tags/?name__iexact=add%20to%20stockbox' => Http::response([
            'results' => [['id' => 9, 'name' => 'add to stockbox']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'tags' => [3, 9, 4]]),
    ]);

    PaperlessClient::fromConfig()->removeTag(42, 'add to stockbox');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['tags'] === [3, 4]);
});

it('setCustomField() writes the value into the named field, preserving other fields', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [
                ['id' => 1, 'name' => 'invoice_number'],
                ['id' => 2, 'name' => 'stockroom_item_ids'],
            ],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'custom_fields' => [
            ['field' => 1, 'value' => 'INV-001'],
        ]]),
    ]);

    PaperlessClient::fromConfig()->setCustomField(42, 'stockroom_item_ids', '17,42');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['custom_fields'] === [
        ['field' => 1, 'value' => 'INV-001'],
        ['field' => 2, 'value' => '17,42'],
    ]);
});

it('setCustomField() replaces the value when the field is already populated', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'stockroom_item_ids']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['id' => 42, 'custom_fields' => [
            ['field' => 2, 'value' => 'old'],
        ]]),
    ]);

    PaperlessClient::fromConfig()->setCustomField(42, 'stockroom_item_ids', 'new');

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && $r['custom_fields'] === [['field' => 2, 'value' => 'new']]);
});

it('getCustomField() returns the value when populated', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'stockroom_item_ids']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['custom_fields' => [
            ['field' => 2, 'value' => '17,42'],
        ]]),
    ]);

    expect(PaperlessClient::fromConfig()->getCustomField(42, 'stockroom_item_ids'))
        ->toBe('17,42');
});

it('getCustomField() returns null when the field exists but is empty on this doc', function () {
    Http::fake([
        'https://paperless.test/api/custom_fields/' => Http::response([
            'results' => [['id' => 2, 'name' => 'stockroom_item_ids']],
        ]),
        'https://paperless.test/api/documents/42/' => Http::response(['custom_fields' => []]),
    ]);

    expect(PaperlessClient::fromConfig()->getCustomField(42, 'stockroom_item_ids'))->toBeNull();
});
