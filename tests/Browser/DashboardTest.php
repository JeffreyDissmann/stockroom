<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
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

it('surfaces waiting suggestions on the dashboard', function () {
    $this->actingAs(User::factory()->create());

    $box = Item::factory()->create(['name' => 'Moving box']);
    ItemProposal::factory()->for($box)->create();

    $page = visit('/dashboard');

    $page->assertPresent('@dashboard-proposals-card')
        ->assertPresent('@dashboard-proposals-row')
        ->assertSee('Moving box')
        ->assertNoJavaScriptErrors();
});

it('leaves the dashboard alone when there is nothing to review', function () {
    $this->actingAs(User::factory()->create());

    // An all-clear card would just be noise, so it must be absent entirely.
    $page = visit('/dashboard');

    $page->assertMissing('@dashboard-proposals-card')
        ->assertNoJavaScriptErrors();
});

it('titles the card after unread photos when no suggestion is pending', function () {
    $this->actingAs(User::factory()->create());

    ItemImage::factory()->for(Item::factory()->create())->create(['analyzed_at' => null]);

    // Nothing is pending, so "Suggestions waiting (0)" would be both wrong and
    // a discouraging way to report that there is work available.
    $page = visit('/dashboard');

    $page->assertPresent('@dashboard-proposals-card')
        ->assertSee('Photos to review')
        ->assertDontSee('Suggestions waiting')
        ->assertNoJavaScriptErrors();
});
