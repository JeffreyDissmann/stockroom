<?php

declare(strict_types=1);

use App\Enums\ProposalField;
use App\Enums\ProposalStatus;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('casts the field, status and source images', function () {
    $proposal = ItemProposal::factory()->create(['source_image_ids' => [4, 7]]);

    expect($proposal->field)->toBe(ProposalField::Description)
        ->and($proposal->status)->toBe(ProposalStatus::Pending)
        ->and($proposal->source_image_ids)->toBe([4, 7]);
});

it('lists only pending proposals for review', function () {
    ItemProposal::factory()->count(2)->create();
    ItemProposal::factory()->accepted()->create();
    ItemProposal::factory()->rejected()->create();

    expect(ItemProposal::pending()->count())->toBe(2);
});

it('notices when the item changed under a pending proposal', function () {
    // The queue is reviewed at leisure, so someone can edit the item by hand in
    // the meantime. Accepting blindly would silently undo that edit.
    $item = Item::factory()->create(['manufacturer' => 'Bosch']);
    $proposal = ItemProposal::factory()->for($item)->forField(ProposalField::Manufacturer, 'Makita')->create([
        'current_value' => 'Bosch',
    ]);

    expect($proposal->isStale())->toBeFalse();

    $item->update(['manufacturer' => 'Hilti']);

    expect($proposal->fresh()->isStale())->toBeTrue();
});

it('treats a null field and an empty snapshot as unchanged', function () {
    // A description proposed against an empty field is the common case; null vs
    // '' must not read as a concurrent edit.
    $item = Item::factory()->create(['description' => null]);
    $proposal = ItemProposal::factory()->for($item)->create(['current_value' => null]);

    expect($proposal->isStale())->toBeFalse();
});

it('drops proposals when the item is deleted', function () {
    $item = Item::factory()->create();
    ItemProposal::factory()->for($item)->create();

    $item->delete();

    expect(ItemProposal::count())->toBe(0);
});

it('keeps the proposal when the reviewer is deleted', function () {
    // The audit trail outlives the account: losing a user must not erase what
    // was decided about the inventory.
    $user = User::factory()->admin()->create();
    $proposal = ItemProposal::factory()->accepted()->create(['reviewed_by' => $user->id]);

    $user->delete();

    expect($proposal->fresh())->not->toBeNull()
        ->and($proposal->fresh()->reviewed_by)->toBeNull();
});

it('exposes proposals from the item, newest first', function () {
    $item = Item::factory()->create();
    $old = ItemProposal::factory()->for($item)->create(['created_at' => now()->subDay()]);
    $new = ItemProposal::factory()->for($item)->create();

    expect($item->proposals->pluck('id')->all())->toBe([$new->id, $old->id]);
});

it('records when an image was last reviewed', function () {
    $image = ItemImage::factory()->create();

    expect($image->analyzed_at)->toBeNull();

    $image->update(['analyzed_at' => now()]);

    expect($image->fresh()->analyzed_at)->not->toBeNull();
});

it('lists only the photos never looked at', function () {
    $item = Item::factory()->create();
    ItemImage::factory()->for($item)->count(2)->create();
    ItemImage::factory()->for($item)->analyzed()->create();

    expect(ItemImage::unreviewed()->count())->toBe(2);
});
