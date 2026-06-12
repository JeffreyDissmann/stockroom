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
