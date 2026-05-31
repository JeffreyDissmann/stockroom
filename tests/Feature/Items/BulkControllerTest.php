<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Jobs\ReindexItemsJob;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('requires authentication', function () {
    auth()->logout();

    $item = Item::factory()->create();

    $this->post('/items/bulk', ['action' => 'delete', 'ids' => [$item->id]])
        ->assertRedirect('/login');
});

it('validates the action whitelist', function () {
    $item = Item::factory()->create();

    $this->post('/items/bulk', ['action' => 'eat', 'ids' => [$item->id]])
        ->assertSessionHasErrors('action');
});

it('bulk-deletes the listed items', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $keep = Item::factory()->create();

    $this->post('/items/bulk', ['action' => 'delete', 'ids' => [$a->id, $b->id]])
        ->assertRedirect()
        ->assertSessionHas('bulk_result.action', 'delete')
        ->assertSessionHas('bulk_result.count', 2);

    expect(Item::query()->whereIn('id', [$a->id, $b->id])->count())->toBe(0)
        ->and(Item::query()->find($keep->id))->not->toBeNull();
});

it('bulk-moves items to the given parent', function () {
    $room = Item::factory()->room()->create();
    $a = Item::factory()->create(['parent_id' => null]);
    $b = Item::factory()->create(['parent_id' => null]);

    $this->post('/items/bulk', ['action' => 'move', 'ids' => [$a->id, $b->id], 'parent_id' => $room->id])
        ->assertRedirect()
        ->assertSessionHas('bulk_result.action', 'move');

    expect($a->fresh()->parent_id)->toBe($room->id)
        ->and($b->fresh()->parent_id)->toBe($room->id);
});

it('flashes the previous-parent map after a bulk move so the UI can undo', function () {
    $oldRoom = Item::factory()->room()->create();
    $newRoom = Item::factory()->room()->create();
    $a = Item::factory()->create(['parent_id' => $oldRoom->id]);
    $b = Item::factory()->create(['parent_id' => null]);

    $this->post('/items/bulk', ['action' => 'move', 'ids' => [$a->id, $b->id], 'parent_id' => $newRoom->id])
        ->assertRedirect()
        ->assertSessionHas('bulk_result.previous', [
            $a->id => $oldRoom->id,
            $b->id => null,
        ]);
});

it('bulk-attaches a tag to every listed item idempotently', function () {
    $tag = Tag::factory()->create();
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    // b already has the tag — attach should be a no-op for it.
    $b->tags()->attach($tag->id);

    $this->post('/items/bulk', ['action' => 'attach-tag', 'ids' => [$a->id, $b->id], 'tag_id' => $tag->id])
        ->assertRedirect();

    expect($a->fresh()->tags->pluck('id')->all())->toBe([$tag->id])
        ->and($b->fresh()->tags()->count())->toBe(1); // not duplicated
});

it('bulk-detaches a tag from every listed item', function () {
    $tag = Tag::factory()->create();
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $a->tags()->attach($tag->id);

    $this->post('/items/bulk', ['action' => 'detach-tag', 'ids' => [$a->id, $b->id], 'tag_id' => $tag->id])
        ->assertRedirect();

    expect($a->fresh()->tags()->count())->toBe(0)
        ->and($b->fresh()->tags()->count())->toBe(0);
});

it('requires tag_id on tag operations', function () {
    $item = Item::factory()->create();

    $this->post('/items/bulk', ['action' => 'attach-tag', 'ids' => [$item->id]])
        ->assertSessionHasErrors('tag_id');
});

it('rejects an empty ids array', function () {
    $this->post('/items/bulk', ['action' => 'delete', 'ids' => []])
        ->assertSessionHasErrors('ids');
});

it('rejects non-existent item ids', function () {
    $this->post('/items/bulk', ['action' => 'delete', 'ids' => [9999]])
        ->assertSessionHasErrors('ids.0');
});

it('refuses to bulk-delete the item configured as the Paperless intake parent', function () {
    $room = Item::factory()->room()->create();
    $other = Item::factory()->create();
    Setting::set('paperless_parent_id', $room->id);

    $this->post('/items/bulk', ['action' => 'delete', 'ids' => [$room->id, $other->id]])
        ->assertSessionHasErrors('ids');

    // Both items still exist — the whole batch is refused, not just $room.
    expect(Item::query()->whereIn('id', [$room->id, $other->id])->count())->toBe(2);
});

it('refuses to bulk-move an item into itself', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $this->post('/items/bulk', ['action' => 'move', 'ids' => [$a->id, $b->id], 'parent_id' => $a->id])
        ->assertSessionHasErrors('parent_id');

    expect($b->fresh()->parent_id)->not->toBe($a->id);
});

it('refuses to bulk-move an item into one of its own descendants', function () {
    $room = Item::factory()->room()->create();
    $box = Item::factory()->create(['type' => ItemType::Container, 'parent_id' => $room->id]);

    // Trying to move the room into the box (which is inside the room) should fail.
    $this->post('/items/bulk', ['action' => 'move', 'ids' => [$room->id], 'parent_id' => $box->id])
        ->assertSessionHasErrors('parent_id');

    expect($room->fresh()->parent_id)->toBeNull();
});

it('dispatches a ReindexItemsJob after a successful bulk move', function () {
    Bus::fake();

    $room = Item::factory()->room()->create();
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $this->post('/items/bulk', ['action' => 'move', 'ids' => [$a->id, $b->id], 'parent_id' => $room->id])
        ->assertRedirect();

    // The reindex job carries every moved item id; descendants would be
    // added too but there aren't any here.
    Bus::assertDispatched(ReindexItemsJob::class, function (ReindexItemsJob $job) use ($a, $b): bool {
        // `itemIds` is readonly — copy before sorting.
        $actual = $job->itemIds;
        sort($actual);
        $expected = [$a->id, $b->id];
        sort($expected);

        return $actual === $expected;
    });
});
