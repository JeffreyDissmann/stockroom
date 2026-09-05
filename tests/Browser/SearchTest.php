<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('clears active filters from the search page', function () {
    Item::factory()->room()->create(['name' => 'Garage']);

    // Arriving with a type filter active surfaces the Clear control.
    $page = visit('/search?type=room');

    $page->assertPresent('@clear-filters')
        ->click('@clear-filters')
        ->assertMissing('@clear-filters')
        ->assertSee('Garage')
        ->assertNoJavaScriptErrors();
});

it('names the page and shares the empty-state card with every other list', function () {
    // Search was the only page that opened straight into its filter bar with
    // nothing naming it, and its empty state was one of three near-identical
    // hand-rolled variants.
    $page = visit('/search');

    $page->assertSee('Search')
        ->assertPresent('@empty-state')
        ->assertNoJavaScriptErrors();
});

it('paginates through activity with the shared control', function () {
    Item::factory()->count(30)->create();

    $page = visit('/activity');

    $page->assertPresent('@pagination')
        ->assertNoJavaScriptErrors();
});
