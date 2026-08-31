<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * The inventory list, a container's contents and the search results all render
 * the same card component, so they have to be sent the same shape. They were
 * previously built by three hand-rolled serialisers that had drifted: search
 * omitted `is_sold` entirely and counted sold children.
 *
 * These pin the contract at each surface rather than testing ItemResource in
 * isolation, because the bug was never in one serialiser — it was in the
 * distance between them.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** @return list<string> */
function cardKeys(array $card): array
{
    $keys = array_keys($card);
    sort($keys);

    return $keys;
}

it('sends the same card keys on the inventory list and inside a container', function () {
    $room = Item::factory()->room()->create();
    $tag = Tag::factory()->create();
    Item::factory()->create(['parent_id' => $room->id])->tags()->attach($tag);

    $inRoom = null;
    $this->get("/items/{$room->id}")->assertInertia(function (AssertableInertia $page) use (&$inRoom) {
        $inRoom = $page->toArray()['props']['children'][0];
    });

    $topLevel = null;
    $this->get('/items')->assertInertia(function (AssertableInertia $page) use (&$topLevel) {
        $topLevel = $page->toArray()['props']['items'][0];
    });

    expect(cardKeys($inRoom))->toBe(cardKeys($topLevel));
});

it('marks a sold item as sold wherever it surfaces', function () {
    $room = Item::factory()->room()->create();
    Item::factory()->create(['name' => 'Old Mower', 'parent_id' => $room->id, 'sold_date' => now()]);

    // The archive inside a room...
    $this->get("/items/{$room->id}?sold=1")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('children.0.is_sold', true));

    // ...and the same item found through search.
    $this->get('/search?sold=only')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('items.data.0.is_sold', true));
});

it('counts owned children the same way on every surface', function () {
    $box = Item::factory()->create(['type' => 'container', 'name' => 'Crate']);
    Item::factory()->create(['parent_id' => $box->id]);
    Item::factory()->create(['parent_id' => $box->id, 'sold_date' => now()]);

    $this->get('/items')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('items.0.children_count', 1));

    $this->get('/search?type=container')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('items.data.0.children_count', 1));
});
