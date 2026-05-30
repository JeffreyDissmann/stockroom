<?php

declare(strict_types=1);

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

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

it('logs link_added activity on both items, with the partner in properties', function () {
    $a = Item::factory()->create(['name' => 'Camera']);
    $b = Item::factory()->create(['name' => 'Camera box']);

    $a->linkRelated($b);

    $rows = Activity::query()
        ->where('event', 'link_added')
        ->get();

    expect($rows)->toHaveCount(2);

    $onA = $rows->firstWhere('subject_id', $a->id);
    $onB = $rows->firstWhere('subject_id', $b->id);

    expect($onA->properties['related_id'])->toBe($b->id)
        ->and($onA->properties['related_name'])->toBe('Camera box')
        ->and($onB->properties['related_id'])->toBe($a->id)
        ->and($onB->properties['related_name'])->toBe('Camera');
});

it('does not log when relinking an already-linked pair (idempotent feed)', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $a->linkRelated($b);

    $before = Activity::query()->where('event', 'link_added')->count();
    $a->linkRelated($b); // second call should be a no-op for the activity feed
    $after = Activity::query()->where('event', 'link_added')->count();

    expect($after)->toBe($before);
});

it('logs link_removed activity on both items on unlink', function () {
    $a = Item::factory()->create(['name' => 'Camera']);
    $b = Item::factory()->create(['name' => 'Camera box']);
    $a->linkRelated($b);

    $a->unlinkRelated($b);

    $rows = Activity::query()
        ->where('event', 'link_removed')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('subject_id')->sort()->values()->all())
        ->toEqual(collect([$a->id, $b->id])->sort()->values()->all());
});

it('does not log when unlinking an already-unlinked pair', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $a->unlinkRelated($b); // no link existed, no activity should be written

    expect(Activity::query()->where('event', 'link_removed')->count())->toBe(0);
});
