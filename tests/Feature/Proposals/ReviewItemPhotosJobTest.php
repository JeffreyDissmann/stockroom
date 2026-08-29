<?php

declare(strict_types=1);

use App\Jobs\ReviewItemPhotosJob;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use App\Services\Proposals\Exceptions\PhotoReviewFailed;
use App\Services\Proposals\PhotoReviewRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('reviews what is waiting and reports progress as it goes', function () {
    $item = Item::factory()->create();
    ItemImage::factory()->for($item)->create(['analyzed_at' => null]);

    $this->mock(PhotoReviewRunner::class)
        ->shouldReceive('review')
        ->once()
        ->andReturn(collect([new ItemProposal, new ItemProposal]));

    (new ReviewItemPhotosJob)->handle(app(PhotoReviewRunner::class));

    expect(Cache::get(ReviewItemPhotosJob::STATUS_KEY))->toMatchArray([
        'state' => 'done',
        'done' => 1,
        'total' => 1,
        'proposed' => 2,
        'failed' => 0,
    ]);
});

it('keeps going when one item cannot be read', function () {
    // A model hiccup on one item must not cost the rest of the run: the whole
    // point of the button is to clear the queue in one press.
    ItemImage::factory()->for(Item::factory()->create())->create(['analyzed_at' => null]);
    ItemImage::factory()->for(Item::factory()->create())->create(['analyzed_at' => null]);

    $this->mock(PhotoReviewRunner::class)
        ->shouldReceive('review')
        ->twice()
        ->andThrow(new PhotoReviewFailed('nope'));

    (new ReviewItemPhotosJob)->handle(app(PhotoReviewRunner::class));

    expect(Cache::get(ReviewItemPhotosJob::STATUS_KEY))->toMatchArray([
        'state' => 'done',
        'done' => 2,
        'failed' => 2,
        'proposed' => 0,
    ]);
});

it('records a failure so the page does not wait forever', function () {
    (new ReviewItemPhotosJob)->failed(new RuntimeException('ollama unreachable'));

    expect(Cache::get(ReviewItemPhotosJob::STATUS_KEY))->toMatchArray([
        'state' => 'failed',
        'error' => 'ollama unreachable',
    ]);
});
