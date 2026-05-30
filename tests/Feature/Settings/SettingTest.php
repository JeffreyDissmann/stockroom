<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('round-trips scalar values', function () {
    Setting::set('greeting', 'hello');
    Setting::set('count', 42);
    Setting::set('flag', true);

    expect(Setting::get('greeting'))->toBe('hello')
        ->and(Setting::get('count'))->toBe(42)
        ->and(Setting::get('flag'))->toBeTrue();
});

it('round-trips array values', function () {
    Setting::set('list', ['a', 'b', 'c']);

    expect(Setting::get('list'))->toBe(['a', 'b', 'c']);
});

it('returns the default for a missing key', function () {
    expect(Setting::get('nope'))->toBeNull()
        ->and(Setting::get('nope', 'fallback'))->toBe('fallback');
});

it('upserts when the same key is set twice', function () {
    Setting::set('counter', 1);
    Setting::set('counter', 2);

    expect(Setting::get('counter'))->toBe(2)
        ->and(Setting::query()->where('key', 'counter')->count())->toBe(1);
});

it('boots a default Box tag and points the box_tag_id setting at it', function () {
    // The migration runs as part of RefreshDatabase. The box tag exists, and
    // the setting points at it — no additional seeding needed.
    $boxTag = Tag::query()->where('name', 'Box')->first();

    expect($boxTag)->not->toBeNull()
        ->and(Setting::get('box_tag_id'))->toBe($boxTag->id);
});
