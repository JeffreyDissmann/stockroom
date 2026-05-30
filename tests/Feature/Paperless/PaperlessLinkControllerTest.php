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
