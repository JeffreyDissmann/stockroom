<?php

declare(strict_types=1);

use App\Models\Item;
use App\Services\Brave\AttachFirstImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function braveResultBody(string $imageUrl): array
{
    return [
        'results' => [[
            'title' => 'Sample',
            'url' => 'https://shop.example/p',
            'thumbnail' => ['src' => 'https://img.example/thumb.jpg'],
            'properties' => ['url' => $imageUrl],
        ]],
    ];
}

it('does nothing when Brave is not configured', function () {
    config(['services.brave.key' => '']);
    Http::preventStrayRequests();
    Http::fake();

    $item = Item::factory()->create();
    $attached = app(AttachFirstImage::class)($item);

    expect($attached)->toBeFalse()
        ->and($item->fresh()->images()->count())->toBe(0);
    Http::assertNothingSent();
});

it('does nothing when the item has no usable search query', function () {
    config(['services.brave.key' => 'TESTKEY']);
    Http::preventStrayRequests();
    Http::fake();

    // Empty name, manufacturer and model_number all blank → defaultImageSearchQuery() === ''.
    $item = Item::factory()->create(['name' => '', 'manufacturer' => null, 'model_number' => null]);
    $attached = app(AttachFirstImage::class)($item);

    expect($attached)->toBeFalse();
    Http::assertNothingSent();
});

it('downloads the first Brave result and attaches it as the primary image', function () {
    config(['services.brave.key' => 'TESTKEY']);

    $jpeg = UploadedFile::fake()->image('photo.jpg', 80, 80)->getContent();
    Http::fake([
        'api.search.brave.com/*' => Http::response(braveResultBody('https://img.example/full.jpg')),
        'img.example/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $item = Item::factory()->create(['name' => 'Sonos Roam']);
    $attached = app(AttachFirstImage::class)($item);

    expect($attached)->toBeTrue();

    $images = $item->fresh()->images()->get();
    expect($images)->toHaveCount(1)
        ->and($images[0]->is_primary)->toBeTrue();

    // Brave call carried the auth header and the item's distilled query.
    Http::assertSent(fn ($r) => str_contains($r->url(), 'api.search.brave.com')
        && $r->hasHeader('X-Subscription-Token', 'TESTKEY')
        && str_contains(urldecode($r->url()), 'q=Sonos Roam'));
});

it('logs and returns false when Brave returns zero results', function () {
    config(['services.brave.key' => 'TESTKEY']);
    Http::fake(['api.search.brave.com/*' => Http::response(['results' => []])]);

    $item = Item::factory()->create(['name' => 'Unknown thing']);
    expect(app(AttachFirstImage::class)($item))->toBeFalse()
        ->and($item->fresh()->images()->count())->toBe(0);
});

it('does not throw when the image download fails', function () {
    config(['services.brave.key' => 'TESTKEY']);
    Http::fake([
        'api.search.brave.com/*' => Http::response(braveResultBody('https://img.example/full.jpg')),
        'img.example/*' => Http::response('not an image', 200, ['Content-Type' => 'text/html']),
    ]);

    $item = Item::factory()->create(['name' => 'Sonos Roam']);
    expect(app(AttachFirstImage::class)($item))->toBeFalse()
        ->and($item->fresh()->images()->count())->toBe(0);
});
