<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\MaintenanceTask;
use App\Models\Tag;
use App\Models\User;
use App\Services\InventoryStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sold is an archive, not a status. These pin the rule at each place that used
 * to treat a sold item as owned — the dashboard counted it, reminders fired
 * for it, and the photo agent spent inference describing it.
 */
it('leaves sold items out of the counts, so they agree with the value', function () {
    Item::factory()->create(['purchase_price' => 100]);
    Item::factory()->create(['purchase_price' => 50, 'sold_date' => now()]);

    $stats = app(InventoryStatistics::class);

    expect($stats->countsByType()['item'])->toBe(1)
        ->and($stats->ownedValue())->toBe(100.0);
});

it('leaves sold items out of tag and room counts', function () {
    $garage = Item::factory()->room()->create();
    $tag = Tag::factory()->create();

    $kept = Item::factory()->create(['parent_id' => $garage->id]);
    $gone = Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);
    $kept->tags()->attach($tag);
    $gone->tags()->attach($tag);

    $stats = app(InventoryStatistics::class);

    expect($stats->tagsWithItemCounts()->first()->items_count)->toBe(1)
        ->and($stats->roomsWithChildCounts()->first()->children_count)->toBe(1);
});

it('does not remind you to service something you sold', function () {
    $sold = Item::factory()->create(['sold_date' => now()]);
    MaintenanceTask::factory()->for($sold)->create(['next_due_at' => today()->subDay()]);

    expect(MaintenanceTask::needingAttention())->toBeEmpty();
});

it('still reminds you about what you kept', function () {
    $kept = Item::factory()->create();
    MaintenanceTask::factory()->for($kept)->create(['next_due_at' => today()->subDay()]);

    expect(MaintenanceTask::needingAttention())->toHaveCount(1);
});

it('does not spend vision inference on a sold item, photos or not', function () {
    $sold = Item::factory()->create(['sold_date' => now()]);
    ItemImage::factory()->for($sold)->create(['analyzed_at' => null]);

    $kept = Item::factory()->create();
    ItemImage::factory()->for($kept)->create(['analyzed_at' => null]);

    expect(Item::awaitingPhotoReview()->pluck('id')->all())->toBe([$kept->id]);
});

it('keeps the room a sold item was in', function () {
    // The parent is deliberately untouched: which room it was in when it went
    // is a fact worth keeping, and a mistaken sale could never recover it.
    $garage = Item::factory()->room()->create();
    $sold = Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);

    expect($sold->fresh()->parent_id)->toBe($garage->id);
});

it('can still find the archive', function () {
    Item::factory()->create();
    $sold = Item::factory()->create(['sold_date' => now()]);

    expect(Item::sold()->pluck('id')->all())->toBe([$sold->id]);
});

it('keeps sold items out of the inventory list', function () {
    $garage = Item::factory()->room()->create();
    $kept = Item::factory()->create(['parent_id' => $garage->id]);
    Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get("/items?parent={$garage->id}")
        ->assertInertia(fn ($page) => $page
            ->has('items', 1)
            ->where('items.0.id', $kept->id)
            ->where('soldCount', 1)
            ->where('includeSold', false));
});

it('shows the archive when asked', function () {
    $garage = Item::factory()->room()->create();
    Item::factory()->create(['parent_id' => $garage->id]);
    Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get("/items?parent={$garage->id}&sold=1")
        ->assertInertia(fn ($page) => $page->has('items', 2)->where('includeSold', true));
});

it('marks a sold item as such wherever it surfaces', function () {
    $garage = Item::factory()->room()->create();
    Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get("/items?parent={$garage->id}&sold=1")
        ->assertInertia(fn ($page) => $page->where('items.0.is_sold', true));
});

it('still lists a sold item among related items', function () {
    // Deliberately exempt: a related link is a statement about two things,
    // and selling one does not make the connection untrue or unhelpful.
    $item = Item::factory()->create();
    $sold = Item::factory()->create(['sold_date' => now()]);
    $item->relatedItems()->attach($sold->id);

    test()->actingAs(User::factory()->create())
        ->get("/items/{$item->id}")
        ->assertInertia(fn ($page) => $page->has('relatedItems', 1)->where('relatedItems.0.is_sold', true));
});

it('keeps sold items out of search results', function () {
    $kept = Item::factory()->create(['name' => 'Bicycle']);
    Item::factory()->create(['name' => 'Bicycle pump', 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get('/search')
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.id', $kept->id)
            ->where('filters.sold', null));
});

it('widens the search to include the archive', function () {
    Item::factory()->create(['name' => 'Bicycle']);
    Item::factory()->create(['name' => 'Bicycle pump', 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get('/search?sold=include')
        ->assertInertia(fn ($page) => $page->has('items.data', 2)->where('filters.sold', 'include'));
});

it('searches the archive alone', function () {
    // "What did I sell, and for how much" is a different question from
    // searching the inventory, so the archive can be the whole answer.
    Item::factory()->create(['name' => 'Bicycle']);
    $sold = Item::factory()->create(['name' => 'Bicycle pump', 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get('/search?sold=only')
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.id', $sold->id)
            ->where('filters.sold', 'only'));
});

it('ignores a nonsense sold filter rather than showing everything', function () {
    Item::factory()->create(['name' => 'Bicycle']);
    Item::factory()->create(['name' => 'Bicycle pump', 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get('/search?sold=everything')
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('filters.sold', null));
});

it('keeps sold items out of a room\'s contents', function () {
    // The bug this pins: the inventory list was filtered but the Contents
    // section on a room's own page was not, so a sold item stayed visible
    // exactly where you would look for it.
    $office = Item::factory()->room()->create(['name' => 'Büro']);
    $kept = Item::factory()->create(['parent_id' => $office->id]);
    Item::factory()->create(['parent_id' => $office->id, 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get("/items/{$office->id}")
        ->assertInertia(fn ($page) => $page
            ->has('children', 1)
            ->where('children.0.id', $kept->id)
            ->where('soldCount', 1));
});

it('shows a room\'s archive when asked', function () {
    $office = Item::factory()->room()->create();
    Item::factory()->create(['parent_id' => $office->id]);
    Item::factory()->create(['parent_id' => $office->id, 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get("/items/{$office->id}?sold=1")
        ->assertInertia(fn ($page) => $page->has('children', 2)->where('includeSold', true));
});

it('will not offer a sold container as somewhere to put things', function () {
    // Saving into one would hide the new item the instant it was created.
    Item::factory()->container()->create(['name' => 'Kept box']);
    Item::factory()->container()->create(['name' => 'Sold box', 'sold_date' => now()]);

    test()->actingAs(User::factory()->create())
        ->get('/items/create')
        ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->pluck('name')->doesntContain('Sold box')));
});
