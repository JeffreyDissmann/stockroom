<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;

it('renders the compact dashboard with stats and sections', function () {
    $this->actingAs(User::factory()->create());

    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->container()->create(['name' => 'Toolbox', 'parent_id' => $garage->id]);
    Item::factory()->create(['name' => 'Bicycle', 'parent_id' => $garage->id]);
    Tag::factory()->create(['name' => 'Tools']);

    $page = visit('/dashboard');

    $page->assertSee('Welcome back')
        ->assertSee('Items')
        ->assertSee('Rooms')
        ->assertSee('Containers')
        ->assertSee('Loose items')
        ->assertSee('Recently added')
        ->assertSee('Garage')
        ->assertSee('Tools')
        ->assertNoJavaScriptErrors();
});

it('navigates from the top nav to inventory and tags', function () {
    $this->actingAs(User::factory()->create());

    $page = visit('/dashboard');

    $page->click('Inventory')
        ->assertPathIs('/items')
        ->assertSee('Inventory')
        ->navigate('/dashboard')
        ->click('Tags')
        ->assertPathIs('/tags')
        ->assertSee('Free-form labels')
        ->assertNoJavaScriptErrors();
});
