<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The picker endpoints (move-targets, related-item-targets) both push
 * filters into Scout via whereIn / whereNotIn. With the test driver set to
 * `collection` (see phpunit.xml), Scout applies those filters in PHP, so
 * these tests validate:
 *   - the controllers wire the filter clauses correctly,
 *   - exclusion behaviour matches the contract (self / descendants for move;
 *     self / already-linked for related),
 *   - type filtering happens for the move endpoint by default.
 *
 * Meilisearch-specific concerns (filterableAttributes config, filter DSL
 * syntax) stay covered by manual browser smoke-tests in dev.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('move-targets excludes the item itself and its descendants', function () {
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    $shelf = Item::factory()->container()->create(['name' => 'Shelf', 'parent_id' => $garage->id]);
    Item::factory()->room()->create(['name' => 'Kitchen']);

    $response = $this->getJson("/items/{$garage->id}/move-targets?q=");
    $ids = collect($response->json('targets'))->pluck('id')->all();

    expect($ids)
        ->not->toContain($garage->id)  // self
        ->not->toContain($shelf->id);  // descendant
});

it('move-targets defaults to rooms and containers only', function () {
    Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->container()->create(['name' => 'Toolbox']);
    Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

    $source = Item::factory()->room()->create(['name' => 'Source']);

    $names = collect($this->getJson("/items/{$source->id}/move-targets?q=")->json('targets'))
        ->pluck('name')->all();

    expect($names)->toContain('Garage', 'Toolbox')->not->toContain('Drill');
});

it('move-targets with all=1 includes items', function () {
    Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);
    $source = Item::factory()->room()->create(['name' => 'Source']);

    $names = collect($this->getJson("/items/{$source->id}/move-targets?all=1")->json('targets'))
        ->pluck('name')->all();

    expect($names)->toContain('Drill');
});

it('related-item-targets returns empty without a query', function () {
    Item::factory()->count(3)->create();
    $source = Item::factory()->create();

    $response = $this->getJson("/items/{$source->id}/related-item-targets?q=");

    expect($response->json('targets'))->toBe([]);
});

it('related-item-targets excludes self and already-linked items', function () {
    $source = Item::factory()->create(['name' => 'Camera']);
    $linked = Item::factory()->create(['name' => 'Camera Lens']);
    $candidate = Item::factory()->create(['name' => 'Camera Tripod']);

    $source->linkRelated($linked);

    $ids = collect($this->getJson("/items/{$source->id}/related-item-targets?q=Camera")->json('targets'))
        ->pluck('id')->all();

    expect($ids)
        ->not->toContain($source->id) // self
        ->not->toContain($linked->id) // already linked
        ->toContain($candidate->id);  // un-linked candidate present
});

it('both endpoints require authentication', function () {
    auth()->logout();
    $item = Item::factory()->create();

    $this->getJson("/items/{$item->id}/move-targets")->assertUnauthorized();
    $this->getJson("/items/{$item->id}/related-item-targets?q=x")->assertUnauthorized();
});
