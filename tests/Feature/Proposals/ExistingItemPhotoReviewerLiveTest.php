<?php

declare(strict_types=1);

use App\Ai\Agents\ExistingItemPhotoReviewer;
use App\Ai\Agents\ItemPhotoFindingsMerger;
use Laravel\Ai\Files\Image;

/**
 * Hits the real Ollama endpoint with real photographs.
 *
 * The faked tests prove the wiring; only this proves the prompts actually get
 * usable answers out of a model. Opt-in, because CI has no Ollama and each
 * vision call costs seconds:
 *
 *   AI_LIVE_TESTS=1 ./vendor/bin/pest tests/Feature/Proposals/ExistingItemPhotoReviewerLiveTest.php
 *
 * Assertions check shape and substance, never exact wording — the model
 * rephrases itself between runs and a test pinned to its prose would fail for
 * no reason.
 */
beforeEach(function () {
    if (getenv('AI_LIVE_TESTS') !== '1') {
        test()->markTestSkipped('Set AI_LIVE_TESTS=1 to run against the real Ollama endpoint.');
    }
});

function fixtureImage(string $name): Image
{
    return Image::fromBase64(
        base64_encode(file_get_contents(base_path("tests/Fixtures/images/{$name}.jpg"))),
        'image/jpeg',
    );
}

/** The map half: one photo, one call. */
function seePhoto(string $item, string $fixture): array
{
    return (new ExistingItemPhotoReviewer($item, 'English'))
        ->prompt('Describe what this photo shows.',
            attachments: [fixtureImage($fixture)], )
        ->toArray();
}

/** The reduce half: text only, no images. */
function mergeFindings(string $item, ?string $existing, array $findings): array
{
    return (new ItemPhotoFindingsMerger($item, $existing, 'English'))
        ->prompt("Observations, one per photo:\n".json_encode($findings, JSON_UNESCAPED_UNICODE))
        ->toArray();
}

it('enumerates what is inside a box so it becomes searchable', function () {
    // The point of the feature: photos are not indexed, so a crate of children's
    // clothes stays unfindable until something writes down what is in it.
    $seen = seePhoto('Moving box', 'collection-box');

    expect($seen['kind'])->toBe('collection')
        ->and($seen['contents'] ?? [])->not->toBeEmpty()
        ->and(strtolower(implode(' ', $seen['contents'] ?? []).' '.($seen['description'] ?? '')))
        ->toMatch('/shoe|sneaker|slipper|cloth|shirt|sock|trouser|jumper|hoodie|knit/');
});

it('reads identifiers off a single object without inventing the rest', function () {
    $seen = seePhoto('Water filter', 'single-object');

    expect($seen['kind'])->toBe('single')
        ->and($seen['manufacturer'])->not->toBeNull()
        // The serial is not legible here. A model returning one has invented it,
        // which is the failure this feature must never ship.
        ->and($seen['serial_number'] ?? null)->toBeNull();
});

it('keeps both photos when two are analysed separately', function () {
    // The reason for map-reduce. Sending both images in ONE structured call was
    // tested and the model read only the last, reproducibly and in order. Analysed
    // apart, neither can be lost.
    $box = seePhoto('Household items', 'collection-box');
    $filter = seePhoto('Household items', 'single-object');

    expect(strtolower(json_encode($box, JSON_UNESCAPED_UNICODE)))->toMatch('/box|bin|container|crate|cloth/')
        ->and(strtolower(json_encode($filter, JSON_UNESCAPED_UNICODE)))->toMatch('/filter|tap|faucet|heater|quooker|cartridge/');
});

it('preserves what the owner wrote and adds what the photo found', function () {
    // Provenance a camera cannot know. Losing it would make the feature
    // destructive rather than additive.
    $merged = mergeFindings('Moving box', 'Bought second-hand in 2019. Kept in the loft.', [
        seePhoto('Moving box', 'collection-box'),
    ]);

    expect(strtolower($merged['description'] ?? ''))
        ->toContain('2019')
        ->toMatch('/loft|attic/');
});

it('unions the contents of two photos of the same box', function () {
    // Two halves of one container, so each photo shows different contents. The
    // merge has to end up with both — this is precisely what the single-call
    // approach lost when it silently read only the last image.
    $left = seePhoto('Moving box', 'box-left');
    $right = seePhoto('Moving box', 'box-right');

    $merged = mergeFindings('Moving box', null, [$left, $right]);

    $seenApart = strtolower(implode(' ', array_merge($left['contents'] ?? [], $right['contents'] ?? [])));
    $afterMerge = strtolower(implode(' ', $merged['contents'] ?? []).' '.($merged['description'] ?? ''));

    // With no existing description there is nothing to be redundant with, so a
    // description is always owed.
    expect($merged['description'])->not->toBeNull()
        ->and($merged['contents'] ?? [])->not->toBeEmpty();

    // Whatever either half saw has to survive into the merged entry.
    $survivors = collect(preg_split('/[^a-z]+/', $seenApart))
        ->filter(fn (string $w): bool => strlen($w) > 4)
        ->filter(fn (string $w): bool => str_contains($afterMerge, $w));

    expect($survivors)->not->toBeEmpty();
});

it('carries an identifier through even when only one photo saw it', function () {
    // A brand legible in one shot and not another is not a disagreement. Dropping
    // it would throw away the most useful thing a photo produced.
    $merged = mergeFindings('Water filter', null, [
        ['kind' => 'single', 'contents' => [], 'description' => 'A stainless tap.', 'manufacturer' => null, 'model_number' => null, 'serial_number' => null],
        ['kind' => 'single', 'contents' => [], 'description' => 'A filter cartridge.', 'manufacturer' => 'Quooker', 'model_number' => null, 'serial_number' => null],
    ]);

    expect($merged['manufacturer'])->toBe('Quooker');
});
