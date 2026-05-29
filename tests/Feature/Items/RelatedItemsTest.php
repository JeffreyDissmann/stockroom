<?php

declare(strict_types=1);

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('writes two pivot rows on link so both sides see each other', function () {
    $a = Item::factory()->create(['name' => 'Camera']);
    $b = Item::factory()->create(['name' => 'Camera box']);

    $a->linkRelated($b);

    expect($a->relatedItems()->pluck('items.id')->all())->toEqual([$b->id])
        ->and($b->relatedItems()->pluck('items.id')->all())->toEqual([$a->id])
        ->and(DB::table('item_relations')->count())->toBe(2);
});

it('is idempotent — relinking the same pair does not duplicate rows', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $a->linkRelated($b);
    $a->linkRelated($b);
    $b->linkRelated($a);

    expect(DB::table('item_relations')->count())->toBe(2);
});

it('removes both directions on unlink', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $a->linkRelated($b);

    $a->unlinkRelated($b);

    expect($a->relatedItems()->count())->toBe(0)
        ->and($b->relatedItems()->count())->toBe(0)
        ->and(DB::table('item_relations')->count())->toBe(0);
});

it('unlink is symmetric — calling from either side works', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $a->linkRelated($b);

    // Unlink from the OTHER side; both rows still go.
    $b->unlinkRelated($a);

    expect(DB::table('item_relations')->count())->toBe(0);
});

it('refuses to link an item to itself', function () {
    $a = Item::factory()->create();

    expect(fn () => $a->linkRelated($a))->toThrow(InvalidArgumentException::class);
});

it('cascades deletes — destroying an item removes its relation rows', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $c = Item::factory()->create();
    $a->linkRelated($b);
    $a->linkRelated($c);

    expect(DB::table('item_relations')->count())->toBe(4); // 2 pairs × 2 directions

    $a->delete();

    // All 4 rows referenced $a's id either as item_id or related_item_id.
    expect(DB::table('item_relations')->count())->toBe(0);
});

it('eager loads via with() — needed for Inertia payload on Show.vue', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $a->linkRelated($b);

    $loaded = Item::with('relatedItems')->find($a->id);

    expect($loaded->relationLoaded('relatedItems'))->toBeTrue()
        ->and($loaded->relatedItems->pluck('id')->all())->toEqual([$b->id]);
});
