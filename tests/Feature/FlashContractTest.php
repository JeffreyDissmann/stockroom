<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * Inertia does not forward session flash on its own — HandleInertiaRequests
 * has to name each key. A key a controller flashes but the middleware omits
 * silently never reaches the page, with nothing failing anywhere.
 *
 * That is how the bulk-move Undo toast came to be dead code: BulkController
 * flashed `bulk_result` from four places, BulkActionBar.vue watched for it,
 * and the middleware dropped it in between. The existing bulk tests passed
 * throughout because they assert `assertSessionHas(...)` — the session, not
 * the prop the component actually reads.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('exposes every declared flash key as a prop, so a watcher always has a shape to bind to', function () {
    $this->get('/dashboard')->assertInertia(fn (AssertableInertia $page) => $page
        ->has('flash.backup')
        ->has('flash.box_created_for')
        ->has('flash.bulk_result')
        ->has('flash.invitation_mail')
        ->has('flash.paperless_relink_count')
        ->has('flash.sale_contents'),
    );
});

it('carries a bulk result through to the prop the Undo toast reads', function () {
    $room = Item::factory()->room()->create();
    $item = Item::factory()->create(['parent_id' => null]);

    $this->post('/items/bulk', ['action' => 'move', 'ids' => [$item->id], 'parent_id' => $room->id]);

    // Follow the redirect the way the browser does: the flash only becomes a
    // prop on the next request.
    $this->get('/items')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('flash.bulk_result.action', 'move')
        ->where('flash.bulk_result.count', 1)
        // The previous-parent map is what Undo replays; null here because the
        // item sat at the top level before the move.
        ->where('flash.bulk_result.previous', [$item->id => null]),
    );
});
