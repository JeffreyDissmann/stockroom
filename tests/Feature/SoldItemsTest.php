<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\MaintenanceTask;
use App\Models\Tag;
use App\Services\InventoryStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Sold is an archive, not a status. These pin the rule at each place that used
 * to treat a sold item as owned — the dashboard counted it, reminders fired
 * for it, and the photo agent spent inference describing it.
 */
it('leaves sold items out of the counts, so they agree with the value', function () {
    Item::factory()->create(['purchase_price' => 100]);
    Item::factory()->create(['purchase_price' => 50, 'sold_date' => now()]);

    $stats = app(InventoryStatistics::class);

    expect($stats->countsByType()['item'])->toBe(1)
        ->and($stats->ownedValue())->toBe(100.0);
});

it('leaves sold items out of tag and room counts', function () {
    $garage = Item::factory()->room()->create();
    $tag = Tag::factory()->create();

    $kept = Item::factory()->create(['parent_id' => $garage->id]);
    $gone = Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);
    $kept->tags()->attach($tag);
    $gone->tags()->attach($tag);

    $stats = app(InventoryStatistics::class);

    expect($stats->tagsWithItemCounts()->first()->items_count)->toBe(1)
        ->and($stats->roomsWithChildCounts()->first()->children_count)->toBe(1);
});

it('does not remind you to service something you sold', function () {
    $sold = Item::factory()->create(['sold_date' => now()]);
    MaintenanceTask::factory()->for($sold)->create(['next_due_at' => today()->subDay()]);

    expect(MaintenanceTask::needingAttention())->toBeEmpty();
});

it('still reminds you about what you kept', function () {
    $kept = Item::factory()->create();
    MaintenanceTask::factory()->for($kept)->create(['next_due_at' => today()->subDay()]);

    expect(MaintenanceTask::needingAttention())->toHaveCount(1);
});

it('does not spend vision inference on a sold item, photos or not', function () {
    $sold = Item::factory()->create(['sold_date' => now()]);
    ItemImage::factory()->for($sold)->create(['analyzed_at' => null]);

    $kept = Item::factory()->create();
    ItemImage::factory()->for($kept)->create(['analyzed_at' => null]);

    expect(Item::awaitingPhotoReview()->pluck('id')->all())->toBe([$kept->id]);
});

it('keeps the room a sold item was in', function () {
    // The parent is deliberately untouched: which room it was in when it went
    // is a fact worth keeping, and a mistaken sale could never recover it.
    $garage = Item::factory()->room()->create();
    $sold = Item::factory()->create(['parent_id' => $garage->id, 'sold_date' => now()]);

    expect($sold->fresh()->parent_id)->toBe($garage->id);
});

it('can still find the archive', function () {
    Item::factory()->create();
    $sold = Item::factory()->create(['sold_date' => now()]);

    expect(Item::sold()->pluck('id')->all())->toBe([$sold->id]);
});
