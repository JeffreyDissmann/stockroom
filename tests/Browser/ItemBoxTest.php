<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the Create-box trigger on the item show page', function () {
    // Canary: the new dialog's trigger button is present and the page is
    // free of render-time template errors. Catches the class of bug where
    // an undefined route helper would silently bail Vue out of the topbar.
    $phone = Item::factory()->create(['name' => 'iPhone 15 Pro']);

    $page = visit("/items/{$phone->id}");

    $page->assertSee('iPhone 15 Pro')
        ->assertPresent('@create-box')
        ->assertNoJavaScriptErrors();
});

it('creates a box via the dialog and shows the success banner on the new box page', function () {
    $phone = Item::factory()->create([
        'type' => ItemType::Item,
        'name' => 'iPhone 15 Pro',
        'manufacturer' => 'Apple',
        'serial_number' => 'SN-12345',
    ]);

    $page = visit("/items/{$phone->id}");

    // The pre-filled name lands in the dialog's input as 'BOX: iPhone 15 Pro'
    // (assertValue, not assertSee — it's in an <input value="…">) and is
    // submittable as-is — confirming the controller's redirect with
    // ?focus=images and a flash payload that surfaces as the green banner.
    $page->click('@create-box')
        ->assertValue('#box-name', 'BOX: iPhone 15 Pro')
        ->click('@box-submit')
        ->assertPresent('@box-created-banner')
        ->assertSee('iPhone 15 Pro') // the source item is named in the banner text
        ->assertNoJavaScriptErrors();

    // The URL on the new box's page must carry ?focus=images — that's what
    // tells Show.vue to auto-open the image search dialog. Pin it down so
    // a future refactor that drops the query param doesn't silently break
    // the "create box → pick photo" handoff.
    expect($page->url())->toContain('focus=images');

    // The DB now has a child Container for the phone.
    expect(Item::query()->where('parent_id', $phone->id)->count())->toBe(1);
});
