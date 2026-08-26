<?php

declare(strict_types=1);

use App\Ai\Agents\ExistingItemPhotoReviewer;
use App\Ai\Agents\ItemPhotoFindingsMerger;
use App\Enums\ProposalField;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use App\Services\Proposals\PhotoReviewRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

/** An item with $count photos whose bytes actually exist on the fake disk. */
function itemWithPhotos(int $count = 1, array $attributes = []): Item
{
    $item = Item::factory()->create($attributes);

    foreach (range(1, $count) as $i) {
        $image = ItemImage::factory()->for($item)->create(['sort_order' => $i]);
        Storage::disk('public')->put($image->largePath(), UploadedFile::fake()->image('p.jpg')->get());
    }

    return $item;
}

function fakeReview(array $perPhoto, array $merged): void
{
    ExistingItemPhotoReviewer::fake($perPhoto);
    ItemPhotoFindingsMerger::fake([$merged]);
}

it('records a description proposal without touching the item', function () {
    // The whole safety model: the agent suggests, the item is unchanged until
    // someone accepts.
    $item = itemWithPhotos(attributes: ['description' => null]);

    fakeReview(
        [['kind' => 'collection', 'contents' => ['wool jumper'], 'description' => 'A box of knitwear.']],
        ['contents' => ['wool jumper'], 'description' => 'A box holding a wool jumper.'],
    );

    $proposals = app(PhotoReviewRunner::class)->review($item);

    expect($proposals)->toHaveCount(1)
        ->and($proposals->first()->field)->toBe(ProposalField::Description)
        ->and($proposals->first()->proposed_value)->toBe('A box holding a wool jumper.')
        ->and($item->fresh()->description)->toBeNull();
});

it('marks the photos reviewed so the next run skips them', function () {
    $item = itemWithPhotos(2);

    fakeReview(
        [['kind' => 'single', 'description' => 'A drill.'], ['kind' => 'single', 'description' => 'A drill.']],
        ['description' => 'A cordless drill.'],
    );

    app(PhotoReviewRunner::class)->review($item);

    expect(ItemImage::unreviewed()->count())->toBe(0);
});

it('does nothing when every photo has already been reviewed', function () {
    $item = Item::factory()->create();
    ItemImage::factory()->for($item)->analyzed()->create();

    expect(app(PhotoReviewRunner::class)->review($item))->toBeEmpty()
        ->and(ItemProposal::count())->toBe(0);
});

it('refuses to propose a mere rewording', function () {
    // The model was asked to stay quiet when it had nothing to add and returned
    // a paraphrase anyway. Unchecked, every nightly run would bury the real
    // finds under near-identical suggestions.
    $item = itemWithPhotos(attributes: ['description' => 'A wool jumper and leather boots.']);

    fakeReview(
        [['kind' => 'collection', 'description' => 'Jumper and boots.']],
        ['description' => 'A  WOOL JUMPER and LEATHER BOOTS. '],
    );

    expect(app(PhotoReviewRunner::class)->review($item))->toBeEmpty();
});

it('refuses a rewrite that drops what the owner wrote', function () {
    // Provenance no camera can recover. A proposal that quietly loses it is
    // worse than no proposal at all.
    $item = itemWithPhotos(attributes: ['description' => 'Inherited from Oma in 2003. Holds winter coats.']);

    fakeReview(
        [['kind' => 'collection', 'description' => 'Winter coats.']],
        ['description' => 'A box holding winter coats and a scarf.'],
    );

    expect(app(PhotoReviewRunner::class)->review($item))->toBeEmpty();
});

it('proposes when the photos genuinely add something', function () {
    $item = itemWithPhotos(attributes: ['description' => 'Holds winter coats.']);

    fakeReview(
        [['kind' => 'collection', 'description' => 'Coats and a scarf.']],
        ['description' => 'Holds winter coats and a woollen scarf.'],
    );

    $proposals = app(PhotoReviewRunner::class)->review($item);

    expect($proposals)->toHaveCount(1)
        ->and($proposals->first()->proposed_value)->toContain('scarf');
});

it('proposes identifiers read off the object', function () {
    $item = itemWithPhotos(attributes: ['description' => 'A drill.', 'manufacturer' => null]);

    fakeReview(
        [['kind' => 'single', 'manufacturer' => 'DeWalt', 'model_number' => 'DCD778']],
        ['description' => null, 'manufacturer' => 'DeWalt', 'model_number' => 'DCD778'],
    );

    $fields = app(PhotoReviewRunner::class)->review($item)->pluck('proposed_value', 'field.value');

    expect($fields->all())->toBe(['manufacturer' => 'DeWalt', 'model_number' => 'DCD778']);
});

it('does not re-propose a value the item already holds', function () {
    $item = itemWithPhotos(attributes: ['manufacturer' => 'DeWalt']);

    fakeReview(
        [['kind' => 'single', 'manufacturer' => 'DeWalt']],
        ['description' => null, 'manufacturer' => 'DeWalt'],
    );

    expect(app(PhotoReviewRunner::class)->review($item))->toBeEmpty();
});

it('replaces its own earlier suggestion rather than stacking them', function () {
    // A nightly re-run must not leave two open proposals for one field.
    $item = itemWithPhotos(attributes: ['description' => 'A box.']);
    ItemProposal::factory()->for($item)->create([
        'field' => ProposalField::Description,
        'proposed_value' => 'An older guess.',
    ]);

    fakeReview(
        [['kind' => 'collection', 'description' => 'A box of tools.']],
        ['description' => 'A box holding assorted tools.'],
    );

    app(PhotoReviewRunner::class)->review($item);

    expect(ItemProposal::pending()->where('item_id', $item->id)->count())->toBe(1)
        ->and(ItemProposal::pending()->first()->proposed_value)->toContain('assorted tools');
});

it('leaves an accepted proposal alone when re-running', function () {
    // History, not clutter: a decision already made must survive later runs.
    $item = itemWithPhotos(attributes: ['description' => 'A box.']);
    ItemProposal::factory()->for($item)->accepted()->create(['field' => ProposalField::Description]);

    fakeReview(
        [['kind' => 'collection', 'description' => 'A box of tools.']],
        ['description' => 'A box holding assorted tools.'],
    );

    app(PhotoReviewRunner::class)->review($item);

    expect(ItemProposal::where('item_id', $item->id)->count())->toBe(2);
});

it('keeps going when one photo fails and proposes from the rest', function () {
    // Three good descriptions and one failure still make a worthwhile proposal;
    // abandoning the item would waste the calls that did succeed.
    $item = itemWithPhotos(2, ['description' => null]);
    ExistingItemPhotoReviewer::fake([
        new RuntimeException('vision endpoint refused'),
        ['kind' => 'collection', 'description' => 'A box of tools.'],
    ]);
    ItemPhotoFindingsMerger::fake([['description' => 'A box holding assorted tools.']]);

    expect(app(PhotoReviewRunner::class)->review($item))->toHaveCount(1);
});

it('leaves the photos unreviewed when every one of them fails', function () {
    // Marking them would write the item off as reviewed when nothing was ever
    // concluded about it, and no later run would look again.
    $item = itemWithPhotos(2);
    ExistingItemPhotoReviewer::fake([
        new RuntimeException('vision endpoint refused'),
        new RuntimeException('vision endpoint refused'),
    ]);

    expect(app(PhotoReviewRunner::class)->review($item))->toBeEmpty()
        ->and(ItemImage::unreviewed()->count())->toBe(2);
});

it('leaves the photos unreviewed when the merge fails', function () {
    // The vision work succeeded; only the cheap text step broke. Retrying it
    // costs one call, whereas writing the item off loses the expensive half.
    $item = itemWithPhotos();
    ExistingItemPhotoReviewer::fake([['kind' => 'single', 'description' => 'A drill.']]);
    ItemPhotoFindingsMerger::fake([new RuntimeException('chat endpoint refused')]);

    expect(app(PhotoReviewRunner::class)->review($item))->toBeEmpty()
        ->and(ItemImage::unreviewed()->count())->toBe(1);
});
