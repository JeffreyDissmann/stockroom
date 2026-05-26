<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use App\Services\Brave\DownloadRejected;
use App\Services\Brave\RemoteImageDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ImageSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.brave.key' => 'TESTKEY']);
    }

    private function braveResult(string $image): array
    {
        return [
            'title' => 'A drill',
            'url' => 'https://shop.example/p',
            'thumbnail' => ['src' => 'https://img.example/thumb.jpg'],
            'properties' => ['url' => $image],
        ];
    }

    private function jpegBytes(): string
    {
        return UploadedFile::fake()->image('photo.jpg', 80, 80)->getContent();
    }

    public function test_search_requires_authentication(): void
    {
        $item = Item::factory()->create();
        $this->getJson("/items/{$item->id}/image-search")->assertUnauthorized();
    }

    public function test_search_returns_normalized_results_and_sends_the_key(): void
    {
        Http::fake(['api.search.brave.com/*' => Http::response([
            'results' => [$this->braveResult('https://img.example/full.jpg')],
        ])]);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

        $this->actingAs(User::factory()->create())
            ->getJson("/items/{$item->id}/image-search?q=cordless+drill")
            ->assertOk()
            ->assertJsonPath('query', 'cordless drill')
            ->assertJsonPath('results.0.thumb_url', 'https://img.example/thumb.jpg')
            ->assertJsonPath('results.0.image_url', 'https://img.example/full.jpg')
            ->assertJsonPath('results.0.source_url', 'https://shop.example/p');

        Http::assertSent(fn ($request) => $request->hasHeader('X-Subscription-Token', 'TESTKEY'));
    }

    public function test_blank_query_distills_a_default_from_the_item(): void
    {
        Http::fake(['api.search.brave.com/*' => Http::response(['results' => []])]);

        $item = Item::factory()->create([
            'type' => ItemType::Item,
            'manufacturer' => 'DeWalt',
            'name' => 'Drill',
            'model_number' => 'DCD777',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/items/{$item->id}/image-search")
            ->assertOk()
            ->assertJsonPath('query', 'DeWalt Drill DCD777');

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q=DeWalt Drill DCD777'));
    }

    public function test_attach_downloads_and_stores_images_marking_the_first_primary(): void
    {
        $jpeg = $this->jpegBytes();
        Http::fake(['img.example/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

        $this->actingAs(User::factory()->create())
            ->post("/items/{$item->id}/images/from-search", [
                'urls' => ['https://img.example/a.jpg', 'https://img.example/b.jpg'],
            ])
            ->assertRedirect();

        $images = $item->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
        Storage::disk('public')->assertExists($images[0]->thumbPath());
    }

    public function test_attaching_images_is_logged_in_the_activity_feed(): void
    {
        $jpeg = $this->jpegBytes();
        Http::fake(['img.example/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg'])]);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

        $this->actingAs(User::factory()->create())
            ->post("/items/{$item->id}/images/from-search", [
                'urls' => ['https://img.example/a.jpg', 'https://img.example/b.jpg'],
            ])
            ->assertRedirect();

        $activity = Activity::query()
            ->whereMorphedTo('subject', $item)
            ->where('event', 'image_added')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame(2, $activity->properties->get('count'));
    }

    public function test_attach_skips_a_non_image_response_without_failing_the_batch(): void
    {
        $jpeg = $this->jpegBytes();
        Http::fake([
            'img.example/good.jpg' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
            'img.example/bad.jpg' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

        $this->actingAs(User::factory()->create())
            ->post("/items/{$item->id}/images/from-search", [
                'urls' => ['https://img.example/bad.jpg', 'https://img.example/good.jpg'],
            ])
            ->assertRedirect();

        $this->assertSame(1, $item->images()->count());
    }

    public function test_attach_errors_when_every_url_fails(): void
    {
        Http::fake(['img.example/*' => Http::response('nope', 200, ['Content-Type' => 'text/html'])]);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

        $this->actingAs(User::factory()->create())
            ->from("/items/{$item->id}")
            ->post("/items/{$item->id}/images/from-search", ['urls' => ['https://img.example/x.jpg']])
            ->assertSessionHasErrors('urls');

        $this->assertSame(0, $item->images()->count());
    }

    public function test_feature_is_disabled_without_a_key(): void
    {
        config(['services.brave.key' => null]);
        Http::fake();

        $item = Item::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->getJson("/items/{$item->id}/image-search")->assertStatus(503);
        $this->actingAs($user)
            ->post("/items/{$item->id}/images/from-search", ['urls' => ['https://img.example/a.jpg']])
            ->assertStatus(503);

        Http::assertNothingSent();
    }

    public function test_urls_are_validated(): void
    {
        $item = Item::factory()->create();
        $user = User::factory()->create();
        $route = "/items/{$item->id}/images/from-search";

        $this->actingAs($user)->from($route)->post($route, ['urls' => []])->assertSessionHasErrors('urls');
        $this->actingAs($user)->from($route)->post($route, ['urls' => array_fill(0, 13, 'https://img.example/a.jpg')])->assertSessionHasErrors('urls');
        $this->actingAs($user)->from($route)->post($route, ['urls' => ['http://img.example/a.jpg']])->assertSessionHasErrors('urls.0');
    }

    public function test_default_image_search_query_helper(): void
    {
        $this->assertSame(
            'DeWalt Drill DCD777',
            (new Item(['manufacturer' => 'DeWalt', 'name' => 'Drill', 'model_number' => 'DCD777']))->defaultImageSearchQuery(),
        );

        $this->assertSame('Saw', (new Item(['name' => 'Saw']))->defaultImageSearchQuery());
    }

    public function test_downloader_rejects_non_https_urls(): void
    {
        $this->expectException(DownloadRejected::class);
        (new RemoteImageDownloader)->download('http://img.example/a.jpg');
    }

    public function test_downloader_rejects_private_addresses(): void
    {
        $this->expectException(DownloadRejected::class);
        (new RemoteImageDownloader)->download('https://127.0.0.1/a.jpg');
    }

    public function test_downloader_rejects_oversized_responses(): void
    {
        Http::fake(['img.example/*' => Http::response('x', 200, ['Content-Length' => (string) (20 * 1024 * 1024)])]);

        $this->expectException(DownloadRejected::class);
        (new RemoteImageDownloader)->download('https://img.example/huge.jpg');
    }

    public function test_downloader_rejects_non_image_bodies(): void
    {
        Http::fake(['img.example/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->expectException(DownloadRejected::class);
        (new RemoteImageDownloader)->download('https://img.example/page.jpg');
    }
}
