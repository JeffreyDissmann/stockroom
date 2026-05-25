<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\User;
use App\Services\ItemImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

class ItemImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_formats_gd_cannot_read_are_converted_via_imagick(): void
    {
        $item = Item::factory()->room()->create();

        // TIFF content GD can't decode, with a .jpg name so the re-encode works:
        // exercises the Imagick fallback in decodeSource().
        $imagick = new Imagick;
        $imagick->newImage(120, 90, new ImagickPixel('#3366cc'));
        $imagick->setImageFormat('tiff');
        $path = tempnam(sys_get_temp_dir(), 'src').'.jpg';
        file_put_contents($path, $imagick->getImageBlob());
        $imagick->clear();

        $image = ItemImageProcessor::default()->store(
            $item,
            new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true),
        );
        @unlink($path);

        $this->assertSame('jpg', $image->extension);
        Storage::disk('public')->assertExists($image->originalPath());
        Storage::disk('public')->assertExists($image->thumbPath());
    }

    public function test_first_uploaded_image_becomes_primary(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();

        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [UploadedFile::fake()->image('one.jpg', 800, 600)],
        ])->assertRedirect();

        $image = $item->images()->firstOrFail();
        $this->assertTrue($image->is_primary);
        $this->assertSame(0, $image->sort_order);
        Storage::disk('public')->assertExists($image->thumbPath());
        Storage::disk('public')->assertExists($image->largePath());
        Storage::disk('public')->assertExists($image->originalPath());
    }

    public function test_second_upload_does_not_become_primary(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();

        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [UploadedFile::fake()->image('one.jpg', 800, 600)],
        ]);
        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [UploadedFile::fake()->image('two.jpg', 800, 600)],
        ]);

        $images = $item->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);
    }

    public function test_set_primary_unsets_siblings(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();
        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
            ],
        ]);

        $second = $item->images()->orderBy('sort_order')->skip(1)->first();
        $this->actingAs($user)
            ->patch("/items/{$item->id}/images/{$second->id}", ['is_primary' => true])
            ->assertRedirect();

        $images = $item->images()->orderBy('sort_order')->get();
        $this->assertFalse($images[0]->is_primary);
        $this->assertTrue($images[1]->is_primary);
    }

    public function test_destroying_primary_promotes_next(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();
        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
            ],
        ]);

        $first = $item->images()->orderBy('sort_order')->first();
        $this->actingAs($user)->delete("/items/{$item->id}/images/{$first->id}")->assertRedirect();

        $remaining = $item->images()->orderBy('sort_order')->get();
        $this->assertCount(1, $remaining);
        $this->assertTrue($remaining->first()->is_primary);
        Storage::disk('public')->assertMissing($first->thumbPath());
    }

    public function test_reorder_updates_sort_order_to_array_index(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();
        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
                UploadedFile::fake()->image('c.jpg', 800, 600),
            ],
        ]);

        $images = $item->images()->orderBy('sort_order')->get();
        $reversed = $images->pluck('id')->reverse()->values()->all();

        $this->actingAs($user)
            ->patch("/items/{$item->id}/images/order", ['ids' => $reversed])
            ->assertRedirect();

        $now = $item->images()->orderBy('sort_order')->pluck('id')->all();
        $this->assertSame($reversed, $now);
    }

    public function test_reorder_rejects_foreign_image_ids(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();
        $other = Item::factory()->room()->create();
        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [UploadedFile::fake()->image('a.jpg', 800, 600)],
        ]);
        $this->actingAs($user)->post("/items/{$other->id}/images", [
            'images' => [UploadedFile::fake()->image('b.jpg', 800, 600)],
        ]);

        $foreignId = $other->images()->first()->id;
        $ownId = $item->images()->first()->id;

        $this->actingAs($user)
            ->from('/')
            ->patch("/items/{$item->id}/images/order", ['ids' => [$ownId, $foreignId]])
            ->assertSessionHasErrors('ids');
    }

    public function test_deleting_item_cascades_images_and_files(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();
        $this->actingAs($user)->post("/items/{$item->id}/images", [
            'images' => [
                UploadedFile::fake()->image('a.jpg', 800, 600),
                UploadedFile::fake()->image('b.jpg', 800, 600),
            ],
        ]);

        $paths = $item->images->map(fn (ItemImage $i) => $i->thumbPath())->all();

        $this->actingAs($user)->delete("/items/{$item->id}")->assertRedirect();

        $this->assertDatabaseCount('item_images', 0);
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_oversized_image_is_rejected(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->room()->create();

        $this->actingAs($user)
            ->from('/')
            ->post("/items/{$item->id}/images", [
                'images' => [UploadedFile::fake()->image('tiny.jpg', 32, 32)],
            ])
            ->assertSessionHasErrors('images.0');
    }

    public function test_image_belonging_to_another_item_404s(): void
    {
        $user = User::factory()->create();
        $itemA = Item::factory()->room()->create();
        $itemB = Item::factory()->room()->create();
        $this->actingAs($user)->post("/items/{$itemB->id}/images", [
            'images' => [UploadedFile::fake()->image('b.jpg', 800, 600)],
        ]);
        $bsImage = $itemB->images()->first();

        $this->actingAs($user)
            ->patch("/items/{$itemA->id}/images/{$bsImage->id}", ['is_primary' => true])
            ->assertStatus(404);
    }
}
