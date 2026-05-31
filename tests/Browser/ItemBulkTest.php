<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;

// Browser-level coverage of the bulk-select flow. The controller path is
// already feature-tested (Tests\Feature\Items\BulkControllerTest); these
// exist to catch render-time regressions in the Vue glue — the toggle
// button, the checkbox overlay, the sticky action bar, the assistant-FAB
// hide while Select mode is on — and to make sure the components mount
// without throwing.

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('renders the Select toggle inline with the view-mode toggle on the items index', function () {
    Item::factory()->room()->create(['name' => 'Garage']);

    $page = visit('/items');

    $page->assertSee('Inventory')
        ->assertPresent('@bulk-toggle')
        ->assertNoJavaScriptErrors();
});

it('enters Select mode and surfaces the sticky bulk action bar after picking an item', function () {
    Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->room()->create(['name' => 'Kitchen']);

    $page = visit('/items');

    $page->click('@bulk-toggle')
        // Selecting "Garage" — click the rendered name; the card is a
        // <button> in Select mode so the click toggles instead of
        // navigating into the item.
        ->click('Garage')
        ->assertPresent('@bulk-action-bar')
        ->assertSeeIn('@bulk-count', '1 selected')
        ->assertPresent('@bulk-delete')
        ->assertPresent('@bulk-move')
        ->assertPresent('@bulk-attach-tag')
        ->assertPresent('@bulk-detach-tag')
        ->assertNoJavaScriptErrors();
});

it('exits Select mode via the Done button and tears the action bar down', function () {
    Item::factory()->room()->create(['name' => 'Garage']);

    $page = visit('/items');

    $page->click('@bulk-toggle')
        ->click('Garage')
        ->assertPresent('@bulk-action-bar')
        ->click('@bulk-toggle') // Now labelled "Done"
        ->assertMissing('@bulk-action-bar')
        ->assertNoJavaScriptErrors();
});

it('renders Select inside the Contents section on item Show, scoped to children', function () {
    // The Select toggle on Show only appears when the item actually has
    // children — empty Contents shouldn't surface a no-op action.
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->container()->create(['name' => 'Toolbox', 'parent_id' => $garage->id]);

    $page = visit("/items/{$garage->id}");

    $page->assertSee('Contents')
        ->assertSee('Toolbox')
        ->assertPresent('@bulk-toggle')
        ->assertNoJavaScriptErrors();
});

it('does not render the Contents Select toggle when the item has no children', function () {
    // Childless rooms / items shouldn't surface a meaningless toggle.
    Item::factory()->room()->create(['name' => 'Empty Room']);
    $empty = Item::query()->where('name', 'Empty Room')->firstOrFail();

    $page = visit("/items/{$empty->id}");

    $page->assertSee('Contents')
        // The page Topbar doesn't have a bulk toggle for Show, and the
        // Contents section's BulkSelectToggle is gated on `children.length`.
        // So with no children, no @bulk-toggle ref appears anywhere.
        ->assertMissing('@bulk-toggle')
        ->assertNoJavaScriptErrors();
});
