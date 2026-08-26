<?php

declare(strict_types=1);

use App\Ai\Agents\ExistingItemPhotoReviewer;
use App\Ai\Agents\ItemPhotoFindingsMerger;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    config(['ai.enabled' => true, 'queue.default' => 'database']);
});

function itemNeedingReview(string $name = 'Moving box'): Item
{
    $item = Item::factory()->create(['name' => $name, 'description' => null]);
    $image = ItemImage::factory()->for($item)->create();
    Storage::disk('public')->put($image->largePath(), UploadedFile::fake()->image('p.jpg')->get());

    return $item;
}

function fakeFinding(string $description = 'A box of tools.'): void
{
    ExistingItemPhotoReviewer::fake([['kind' => 'collection', 'description' => 'Tools.']]);
    ItemPhotoFindingsMerger::fake([['description' => $description]]);
}

it('records proposals for items nobody has reviewed', function () {
    itemNeedingReview();
    fakeFinding();

    $this->artisan('items:review-photos')->assertSuccessful();

    expect(ItemProposal::pending()->count())->toBe(1);
});

it('stands down while the queue still has work', function () {
    // Vision inference is the heaviest thing this app asks of its hardware. If
    // someone is waiting on a queued job, they deserve the GPU more than a
    // background tidy-up does.
    itemNeedingReview();
    fakeFinding();
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time(),
    ]);

    $this->artisan('items:review-photos')
        ->expectsOutputToContain('Skipped')
        ->assertSuccessful();

    expect(ItemProposal::count())->toBe(0)
        ->and(ItemImage::unreviewed()->count())->toBe(1);
});

it('runs anyway when forced', function () {
    itemNeedingReview();
    fakeFinding();
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time(),
    ]);

    $this->artisan('items:review-photos --force')->assertSuccessful();

    expect(ItemProposal::pending()->count())->toBe(1);
});

it('does nothing at all when AI is switched off', function () {
    itemNeedingReview();
    config(['ai.enabled' => false]);

    $this->artisan('items:review-photos')->assertSuccessful();

    expect(ItemProposal::count())->toBe(0)
        ->and(ItemImage::unreviewed()->count())->toBe(1);
});

it('reviews no more items than the limit allows', function () {
    // The cap is what keeps a nightly run bounded: fifteen seconds a photo adds
    // up fast on a household with hundreds of items.
    foreach (range(1, 3) as $i) {
        itemNeedingReview("Box {$i}");
    }
    ExistingItemPhotoReviewer::fake(array_fill(0, 3, ['kind' => 'collection', 'description' => 'Tools.']));
    ItemPhotoFindingsMerger::fake(array_fill(0, 3, ['description' => 'A box of tools.']));

    $this->artisan('items:review-photos --limit=2')->assertSuccessful();

    expect(ItemImage::unreviewed()->count())->toBe(1);
});

it('reports when there is nothing left to review', function () {
    Item::factory()->has(ItemImage::factory()->analyzed(), 'images')->create();

    $this->artisan('items:review-photos')
        ->expectsOutputToContain('Every photo has been reviewed')
        ->assertSuccessful();
});

it('reviews a single item on demand, queue or no queue', function () {
    // The manual path exists so someone can try the agent on one item without
    // waiting for 02:30 or draining the queue first.
    $item = itemNeedingReview();
    fakeFinding();
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time(),
    ]);

    $this->artisan("items:review-photos --item={$item->id}")->assertSuccessful();

    expect(ItemProposal::pending()->count())->toBe(1);
});

it('fails clearly when asked for an item that does not exist', function () {
    $this->artisan('items:review-photos --item=999999')->assertFailed();
});

it('keeps going when one item fails and leaves it for next time', function () {
    // One bad photo must not cost the household its whole nightly run.
    itemNeedingReview('Bad box');
    itemNeedingReview('Good box');
    ExistingItemPhotoReviewer::fake([
        new RuntimeException('vision endpoint refused'),
        ['kind' => 'collection', 'description' => 'Tools.'],
    ]);
    ItemPhotoFindingsMerger::fake([['description' => 'A box of tools.']]);

    $this->artisan('items:review-photos')->assertSuccessful();

    // The failed one keeps its photo unreviewed, so the next run picks it up.
    expect(ItemProposal::pending()->count())->toBe(1)
        ->and(ItemImage::unreviewed()->count())->toBe(1);
});
