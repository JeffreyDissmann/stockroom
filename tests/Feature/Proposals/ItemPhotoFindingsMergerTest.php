<?php

declare(strict_types=1);

use App\Ai\Agents\ItemPhotoFindingsMerger;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;

it('puts what the owner already wrote in front of the model', function () {
    $instructions = (new ItemPhotoFindingsMerger('Moving box', 'Holds winter coats.'))->instructions();

    expect($instructions)->toContain('Moving box')
        ->and($instructions)->toContain('Holds winter coats.');
});

it('says outright when there is no description yet', function () {
    // Without this the prompt would read 'already written this description: ""'
    // and invite the model to preserve an empty string.
    expect((new ItemPhotoFindingsMerger('Drill'))->instructions())
        ->toContain('no description yet');
});

it('asks for prose in the requested language', function () {
    expect((new ItemPhotoFindingsMerger('Drill', null, 'German'))->instructions())
        ->toContain('German');
});

it('returns the merged fields but never a photo shape', function () {
    // `kind` is a per-photo judgement; after merging it means nothing, so it is
    // deliberately absent from this schema.
    $schema = (new ItemPhotoFindingsMerger('Drill'))->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))
        ->toBe(['contents', 'description', 'manufacturer', 'model_number', 'serial_number']);
});

it('reads back a merged result', function () {
    ItemPhotoFindingsMerger::fake([[
        'contents' => ['wool jumper', "children's shoes"],
        'description' => 'Bought in 2019. Holds a wool jumper and children’s shoes.',
        'manufacturer' => null,
        'model_number' => null,
        'serial_number' => null,
    ]]);

    $result = (new ItemPhotoFindingsMerger('Moving box', 'Bought in 2019.'))
        ->prompt('Observations, one per photo: []')->toArray();

    expect($result['contents'])->toHaveCount(2)
        ->and($result['description'])->toContain('2019');
});
