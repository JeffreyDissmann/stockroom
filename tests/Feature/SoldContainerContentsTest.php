<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Browsing is a walk down parent_id and a sold container is not listed, so
 * whatever is inside one would be searchable but unreachable. These pin the
 * two ways out, and the refusal to guess between them.
 */
function sellContainer(Item $container, array $extra = []): TestResponse
{
    return test()->patch("/items/{$container->id}", [
        'name' => $container->name,
        'type' => $container->type->value,
        'sold_date' => now()->toDateString(),
        ...$extra,
    ]);
}

it('refuses to sell a container full of things without being told what happened to them', function () {
    $box = Item::factory()->container()->create();
    Item::factory()->for($box, 'parent')->create();

    sellContainer($box)->assertSessionHasErrors('contents_disposition');

    expect($box->fresh()->sold_date)->toBeNull();
});

it('sells the contents along with the container, all the way down', function () {
    $shelf = Item::factory()->container()->create();
    $box = Item::factory()->container()->for($shelf, 'parent')->create();
    $tool = Item::factory()->for($box, 'parent')->create();

    sellContainer($shelf, ['contents_disposition' => 'sold'])->assertRedirect();

    // Recursively: one checkbox can retire a whole subtree.
    expect($box->fresh()->sold_date)->not->toBeNull()
        ->and($tool->fresh()->sold_date)->not->toBeNull();
});

it('does not copy the sale price down, which would count the money twice', function () {
    $box = Item::factory()->container()->create();
    $tool = Item::factory()->for($box, 'parent')->create();

    sellContainer($box, ['sold_price' => 200, 'sold_to' => 'Anna', 'contents_disposition' => 'sold']);

    expect($tool->fresh()->sold_price)->toBeNull()
        ->and($tool->fresh()->sold_to)->toBe('Anna');
});

it('leaves an already-sold item inside on its own sale date', function () {
    // That was a separate sale; overwriting its date would rewrite history.
    $box = Item::factory()->container()->create();
    $earlier = Item::factory()->for($box, 'parent')->create(['sold_date' => now()->subYear()]);

    sellContainer($box, ['contents_disposition' => 'sold']);

    expect($earlier->fresh()->sold_date->toDateString())->toBe(now()->subYear()->toDateString());
});

it('moves kept contents up to where the container stood', function () {
    $garage = Item::factory()->room()->create();
    $box = Item::factory()->container()->for($garage, 'parent')->create();
    $tool = Item::factory()->for($box, 'parent')->create();

    sellContainer($box, ['contents_disposition' => 'kept'])->assertRedirect();

    expect($tool->fresh()->parent_id)->toBe($garage->id)
        ->and($tool->fresh()->sold_date)->toBeNull();
});

it('sells an empty container without asking anything', function () {
    $box = Item::factory()->container()->create();

    sellContainer($box)->assertRedirect()->assertSessionHasNoErrors();

    expect($box->fresh()->sold_date)->not->toBeNull();
});

it('does not ask again when editing a sale that already happened', function () {
    $box = Item::factory()->container()->create(['sold_date' => now()->subMonth()]);
    Item::factory()->for($box, 'parent')->create();

    sellContainer($box, ['sold_to' => 'Corrected name'])->assertSessionHasNoErrors();
});

it('never asks about a plain item', function () {
    $item = Item::factory()->create();

    test()->patch("/items/{$item->id}", [
        'name' => $item->name,
        'type' => $item->type->value,
        'sold_date' => now()->toDateString(),
    ])->assertSessionHasNoErrors();
});

it('accepts the empty disposition the form always posts', function () {
    // The form sends contents_disposition on every save, blank when there is
    // nothing to decide. Omitting it here, as the tests above do, misses that
    // the in: rule still judges a present-but-empty value — which rejected
    // every ordinary sale until `nullable` was added.
    $item = Item::factory()->create();

    test()->patch("/items/{$item->id}", [
        'name' => $item->name,
        'type' => $item->type->value,
        'sold_date' => now()->toDateString(),
        'contents_disposition' => '',
    ])->assertSessionHasNoErrors();

    expect($item->fresh()->sold_date)->not->toBeNull();
});

it('accepts a blank disposition on an empty container too', function () {
    $box = Item::factory()->container()->create();

    test()->patch("/items/{$box->id}", [
        'name' => $box->name,
        'type' => $box->type->value,
        'sold_date' => now()->toDateString(),
        'contents_disposition' => '',
    ])->assertSessionHasNoErrors();

    expect($box->fresh()->sold_date)->not->toBeNull();
});

it('still rejects a nonsense disposition', function () {
    $item = Item::factory()->create();

    test()->patch("/items/{$item->id}", [
        'name' => $item->name,
        'type' => $item->type->value,
        'contents_disposition' => 'incinerated',
    ])->assertSessionHasErrors('contents_disposition');
});
