<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\PaperlessLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('paperless.link_custom_field', 'stockroom_item_ids');

    $this->actingAs(User::factory()->create());
});

it('deletes the local pivot row and clears the item id from the Paperless custom field', function () {
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    Http::fake(function ($request) use ($item) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, '/api/custom_fields/')) {
            return Http::response(['results' => [['id' => 5, 'name' => 'stockroom_item_ids']]]);
        }
        if (str_contains($url, '/api/documents/547/') && $method === 'GET') {
            // Doc was linked to two items; we're unlinking ours.
            return Http::response([
                'id' => 547,
                'custom_fields' => [
                    ['field' => 5, 'value' => "{$item->id},998"],
                ],
            ]);
        }
        if (str_contains($url, '/api/documents/547/') && $method === 'PATCH') {
            return Http::response([], 200);
        }

        return Http::response([], 404);
    });

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect();

    expect(PaperlessLink::query()->count())->toBe(0);

    // Paperless side: PATCH writes only the OTHER item id (998); our id is gone.
    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/547/')
        && collect($r['custom_fields'])->contains(fn ($entry) => (int) $entry['field'] === 5
            && (string) $entry['value'] === '998'));
});

it('writes null when removing the last linked item id', function () {
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    Http::fake(function ($request) use ($item) {
        $url = $request->url();
        $method = $request->method();
        if (str_contains($url, '/api/custom_fields/')) {
            return Http::response(['results' => [['id' => 5, 'name' => 'stockroom_item_ids']]]);
        }
        if (str_contains($url, '/api/documents/547/') && $method === 'GET') {
            return Http::response(['custom_fields' => [['field' => 5, 'value' => (string) $item->id]]]);
        }
        if (str_contains($url, '/api/documents/547/') && $method === 'PATCH') {
            return Http::response([], 200);
        }

        return Http::response([], 404);
    });

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect();

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && collect($r['custom_fields'])->contains(fn ($entry) => (int) $entry['field'] === 5 && $entry['value'] === null));
});

it('still unlinks locally when the Paperless API errors', function () {
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    Http::fake(['*' => Http::response([], 500)]);

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect();

    expect(PaperlessLink::query()->count())->toBe(0);
});

it('redirects guests to login', function () {
    auth()->logout();
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    $this->delete("/items/{$item->id}/paperless-links/547")->assertRedirect('/login');
});
