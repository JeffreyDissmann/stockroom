<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;

/**
 * Destructive actions used to go through the browser's native confirm(), which
 * headless Chromium auto-dismisses — so none of these flows could be covered at
 * all (see .ai/rules/browser.md). Now that they share one real dialog, they can
 * be: this file exists because the ConfirmDialog made it possible.
 */
it('asks before deleting a tag, and does nothing if you back out', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tag::factory()->create(['name' => 'Power Tools']);

    $page = visit('/tags');

    $page->assertPresent('@tag-delete')
        ->click('@tag-delete')
        // A real dialog, not a browser alert.
        ->assertPresent('@confirm-dialog')
        ->assertSee('Power Tools')
        ->click('@confirm-cancel')
        ->assertNoJavaScriptErrors();

    expect(Tag::where('name', 'Power Tools')->exists())->toBeTrue();
});

it('deletes the tag once confirmed', function () {
    $this->actingAs(User::factory()->admin()->create());
    Tag::factory()->create(['name' => 'Power Tools']);

    $page = visit('/tags');

    $page->click('@tag-delete')
        ->assertPresent('@confirm-dialog')
        ->click('@confirm-accept')
        ->assertNoJavaScriptErrors();

    expect(Tag::where('name', 'Power Tools')->exists())->toBeFalse();
});

it('asks before deleting an item, and deletes it when confirmed', function () {
    $this->actingAs(User::factory()->create());
    $item = Item::factory()->create(['name' => 'Cordless Drill']);

    // Desktop viewport: the inline delete button is xl-only, and the default
    // test viewport is narrower than that.
    $page = visit("/items/{$item->id}")->on()->desktop();

    $page->assertPresent('@item-delete')
        ->click('@item-delete')
        ->assertPresent('@confirm-dialog')
        ->assertSee('Cordless Drill')
        ->click('@confirm-accept')
        ->assertNoJavaScriptErrors();

    expect(Item::find($item->id))->toBeNull();
});
