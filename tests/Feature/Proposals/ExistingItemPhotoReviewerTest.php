<?php

declare(strict_types=1);

use App\Ai\Agents\ExistingItemPhotoReviewer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('anchors the analysis on the item being reviewed', function () {
    expect((new ExistingItemPhotoReviewer('Moving box'))->instructions())
        ->toContain('Moving box');
});

it('restricts the model to the single photo in front of it', function () {
    // Several images alongside a structured schema made the model read only the
    // last one, so each photo is now analysed alone and merged afterwards.
    expect((new ExistingItemPhotoReviewer('Drill'))->instructions())
        ->toContain('ONE photo');
});

it('asks for prose in the requested language', function () {
    expect((new ExistingItemPhotoReviewer('Drill', 'German'))->instructions())
        ->toContain('German');
});

it('offers exactly the two photo shapes and nothing else', function () {
    $schema = (new ExistingItemPhotoReviewer('Drill'))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))
        ->toBe(['kind', 'contents', 'description', 'manufacturer', 'model_number', 'serial_number']);
});

it('reads back a collection result', function () {
    ExistingItemPhotoReviewer::fake([[
        'kind' => 'collection',
        'contents' => ["children's shoes", 'wool jumper'],
        'description' => 'A box of children’s clothing including shoes and a wool jumper.',
        'manufacturer' => null,
        'model_number' => null,
        'serial_number' => null,
    ]]);

    $result = (new ExistingItemPhotoReviewer('Moving box'))->prompt('Review the photos of this item.')->toArray();

    expect($result['kind'])->toBe('collection')
        ->and($result['contents'])->toContain("children's shoes")
        ->and($result['manufacturer'])->toBeNull();
});

it('reads back a single-object result', function () {
    ExistingItemPhotoReviewer::fake([[
        'kind' => 'single',
        'contents' => [],
        'description' => 'A cordless drill.',
        'manufacturer' => 'DeWalt',
        'model_number' => 'DCD778',
        'serial_number' => null,
    ]]);

    $result = (new ExistingItemPhotoReviewer('Drill'))->prompt('Review the photos of this item.')->toArray();

    expect($result['kind'])->toBe('single')
        ->and($result['manufacturer'])->toBe('DeWalt')
        ->and($result['contents'])->toBe([]);
});
