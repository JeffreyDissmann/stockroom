<?php

declare(strict_types=1);

use App\Ai\Agents\DocumentExtractor;
use App\Jobs\ProcessPaperlessDocumentJob;
use App\Models\Item;
use App\Models\PaperlessLink;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('paperless.trigger_tag', 'add to stockbox');
    config()->set('paperless.linked_tag', 'stockbox');
    config()->set('paperless.link_custom_field', 'stockroom_item_ids');
    config()->set('ai.enabled', true);
});

/**
 * Builds an Http::fake() callback that responds to Paperless requests based
 * on (method, URL). GETs and PATCHes both hit /api/documents/{id}/ and need
 * different shapes; URL-keyed fakes only let the last entry win, so this
 * router-style closure is the only sane shape.
 */
function fakePaperless(array $docPayload, int $docId): void
{
    Http::fake(function ($request) use ($docPayload, $docId) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, "/api/documents/{$docId}/") && $method === 'GET') {
            return Http::response($docPayload);
        }
        if (str_contains($url, "/api/documents/{$docId}/") && $method === 'PATCH') {
            return Http::response([], 200);
        }
        if (str_contains($url, '/api/tags/') && str_contains($url, 'add%20to%20stockbox')) {
            return Http::response(['results' => [['id' => 9, 'name' => 'add to stockbox']]]);
        }
        if (str_contains($url, '/api/tags/') && str_contains($url, 'stockbox')) {
            return Http::response(['results' => [['id' => 10, 'name' => 'stockbox']]]);
        }
        if (str_contains($url, '/api/custom_fields/')) {
            return Http::response(['results' => [['id' => 5, 'name' => 'stockroom_item_ids']]]);
        }

        return Http::response([], 404);
    });
}

it('creates one Stockroom item per extracted product (multi-item receipt)', function () {
    // Doc 547 in dev (real receipt) has TWO line items — adapter + bottle
    // warmer. Modelled here as the agent's structured output.
    fakePaperless([
        'id' => 547,
        'title' => 'Rechnung Flaschenwärmer',
        'content' => "RECHNUNG\nbaby-walz\n…\nAdapter10 für NUKPerfMatch 9.99\nFlaschenwärmer 4.0 PRO 79.99\nTotal 89.98",
        'tags' => [9],
        'custom_fields' => [],
    ], 547);

    DocumentExtractor::fake([[
        'items' => [
            [
                'name' => 'NUK PerfectMatch Adapter 10',
                'manufacturer' => 'NUK',
                'model_number' => '8.225.222',
                'serial_number' => null,
                'purchase_price' => 9.99,
                'purchase_date' => '2026-05-22',
                'quantity' => 1,
                'description' => 'Adapter for the PerfectMatch baby bottle line.',
            ],
            [
                'name' => 'baby-walz Flaschenwärmer 4.0 PRO',
                'manufacturer' => 'baby-walz',
                'model_number' => '8.362.173',
                'serial_number' => null,
                'purchase_price' => 79.99,
                'purchase_date' => '2026-05-22',
                'quantity' => 1,
                'description' => 'Bottle warmer.',
            ],
        ],
    ]]);

    (new ProcessPaperlessDocumentJob(547))->handle(app(PaperlessClient::class));

    $items = Item::query()
        ->whereIn('name', ['NUK PerfectMatch Adapter 10', 'baby-walz Flaschenwärmer 4.0 PRO'])
        ->orderBy('name')
        ->get();

    // ASCII order on `name`: capital 'N' (NUK) sorts before lowercase 'b'.
    expect($items)->toHaveCount(2)
        ->and($items[0]->manufacturer)->toBe('NUK')
        ->and($items[0]->model_number)->toBe('8.225.222')
        ->and((string) $items[0]->purchase_price)->toBe('9.99')
        ->and($items[1]->manufacturer)->toBe('baby-walz')
        ->and($items[1]->model_number)->toBe('8.362.173')
        ->and((string) $items[1]->purchase_price)->toBe('79.99');

    // Both items linked to the SAME doc.
    expect(PaperlessLink::query()->where('paperless_document_id', 547)->count())->toBe(2);

    // Paperless side: custom field carries both ids comma-separated.
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/547/')
        && isset($r['custom_fields'])
        && collect($r['custom_fields'])->contains(fn ($entry) => (int) $entry['field'] === 5
            && str_contains((string) $entry['value'], (string) $items[0]->id)
            && str_contains((string) $entry['value'], (string) $items[1]->id)));
});

it('falls back to a single placeholder item when the agent returns no items', function () {
    fakePaperless([
        'id' => 77,
        'title' => 'Unrecognisable scan',
        'content' => '...',
        'tags' => [],
        'custom_fields' => [],
    ], 77);

    DocumentExtractor::fake([['items' => []]]);

    (new ProcessPaperlessDocumentJob(77))->handle(app(PaperlessClient::class));

    expect(Item::query()->where('name', 'Unrecognisable scan')->exists())->toBeTrue()
        ->and(PaperlessLink::query()->where('paperless_document_id', 77)->count())->toBe(1);
});

it('falls back to a single placeholder when the agent throws', function () {
    fakePaperless([
        'id' => 78,
        'title' => 'Some doc',
        'content' => 'gibberish',
        'tags' => [],
        'custom_fields' => [],
    ], 78);

    // No DocumentExtractor::fake() set → calling prompt() throws because
    // no provider is configured to consume it. The job swallows that
    // into the placeholder branch.
    Log::spy();

    (new ProcessPaperlessDocumentJob(78))->handle(app(PaperlessClient::class));

    expect(Item::query()->where('name', 'Some doc')->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => $msg === 'paperless.intake.extraction_failed')
        ->once();
});

it('skips the AI call when ai.enabled is false', function () {
    config()->set('ai.enabled', false);

    fakePaperless([
        'id' => 79,
        'title' => 'Doc',
        'content' => 'something',
        'tags' => [],
        'custom_fields' => [],
    ], 79);

    // Not faking the agent — if the job called it, this would fail.
    (new ProcessPaperlessDocumentJob(79))->handle(app(PaperlessClient::class));

    expect(Item::query()->where('name', 'Doc')->exists())->toBeTrue();
});

it('logs but does not abort when Paperless annotation fails', function () {
    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, '/api/documents/99/') && $method === 'GET') {
            return Http::response(['id' => 99, 'title' => 'X', 'content' => '', 'tags' => [], 'custom_fields' => []]);
        }
        if (str_contains($url, '/api/custom_fields/')) {
            return Http::response([], 500);
        }

        return Http::response([], 404);
    });
    DocumentExtractor::fake([['items' => []]]);

    Log::spy();

    (new ProcessPaperlessDocumentJob(99))->handle(app(PaperlessClient::class));

    // Item still created locally even though Paperless annotation failed.
    expect(Item::query()->where('name', 'X')->exists())->toBeTrue();
    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($msg) => $msg === 'paperless.intake.annotate_failed')
        ->once();
});

it('uses the document title for placeholder name and a synthetic name when title is missing', function () {
    fakePaperless([
        'id' => 88,
        'tags' => [],
        'custom_fields' => [],
        // No title field — should fall back to "Paperless doc 88".
    ], 88);
    DocumentExtractor::fake([['items' => []]]);

    (new ProcessPaperlessDocumentJob(88))->handle(app(PaperlessClient::class));

    expect(Item::query()->where('name', 'Paperless doc 88')->exists())->toBeTrue();
});
