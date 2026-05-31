<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
