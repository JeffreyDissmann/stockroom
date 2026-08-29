<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemProposal;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the review queue and accepts a suggestion', function () {
    $item = Item::factory()->create(['name' => 'Moving box', 'description' => 'Winter coats.']);
    ItemProposal::factory()->for($item)->create([
        'current_value' => 'Winter coats.',
        'proposed_value' => 'Winter coats and a pair of boots.',
    ]);

    $page = visit('/proposals');

    $page->assertSee('Moving box')
        ->assertSee('Winter coats and a pair of boots.')
        ->assertPresent('@proposal-row')
        ->assertNoJavaScriptErrors();
});

it('says so plainly when the queue is empty', function () {
    $page = visit('/proposals');

    $page->assertPresent('@proposals-empty')
        ->assertNoJavaScriptErrors();
});
