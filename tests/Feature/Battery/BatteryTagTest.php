<?php

declare(strict_types=1);

use App\Jobs\ReindexItemsJob;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\Battery\BatteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BatteryService::class);
});

function batteryTag(): ?Tag
{
    $id = Setting::int('battery_tag_id');

    return $id !== null ? Tag::find($id) : null;
}

it('auto-assigns and records a Battery tag on the first reading', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 80, now());

    $tag = batteryTag();
    expect($tag)->not->toBeNull()
        ->and($tag->name)->toBe('Battery')
        ->and($item->tags()->whereKey($tag->id)->exists())->toBeTrue();
});

it('reindexes via a job only when the tag is first attached', function () {
    Bus::fake([ReindexItemsJob::class]);
    $item = Item::factory()->create();

    $this->service->recordReading($item, 80, now()->subDay());
    Bus::assertDispatched(ReindexItemsJob::class, fn (ReindexItemsJob $job): bool => $job->itemIds === [$item->id]);

    // A second reading doesn't re-attach the tag, so no further reindex.
    Bus::assertDispatchedTimes(ReindexItemsJob::class, 1);
    $this->service->recordReading($item, 70, now());
    Bus::assertDispatchedTimes(ReindexItemsJob::class, 1);
});

it('re-adds the tag on the next reading if it was removed (self-heal)', function () {
    $item = Item::factory()->create();
    $this->service->recordReading($item, 80, now()->subDay());

    $item->tags()->detach(batteryTag()->id);
    expect($item->tags()->count())->toBe(0);

    $this->service->recordReading($item, 70, now());

    expect($item->tags()->whereKey(batteryTag()->id)->exists())->toBeTrue();
});

it('keeps the tag after a battery change', function () {
    $item = Item::factory()->create();
    $this->service->recordReading($item, 40, now()->subMonth());

    $this->service->changeBattery($item, now());

    expect($item->tags()->whereKey(batteryTag()->id)->exists())->toBeTrue();
});

it('does not detach other tags when assigning the Battery tag', function () {
    $item = Item::factory()->create();
    $other = Tag::factory()->create();
    $item->tags()->attach($other);

    $this->service->recordReading($item, 80, now());

    expect($item->tags()->whereKey($other->id)->exists())->toBeTrue()
        ->and($item->tags()->whereKey(batteryTag()->id)->exists())->toBeTrue();
});

it('protects the Battery tag from deletion in the Tags UI', function () {
    $item = Item::factory()->create();
    $this->service->recordReading($item, 80, now());
    $tagId = batteryTag()->id;

    $this->actingAs(User::factory()->admin()->create())
        ->from('/tags')
        ->delete("/tags/{$tagId}")
        ->assertSessionHasErrors('tag');

    $this->assertDatabaseHas('tags', ['id' => $tagId]);
});

it('marks the Battery tag locked on the edit form of a battery-tracked item', function () {
    $item = Item::factory()->create();
    $this->service->recordReading($item, 80, now());
    $tagId = batteryTag()->id;

    $this->actingAs(User::factory()->create())
        ->get(route('items.edit', $item))
        ->assertInertia(fn (Assert $page) => $page->where('lockedTagIds', [$tagId]));
});

it('locks no tags on the edit form of an item without battery history', function () {
    $item = Item::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('items.edit', $item))
        ->assertInertia(fn (Assert $page) => $page->where('lockedTagIds', []));
});
