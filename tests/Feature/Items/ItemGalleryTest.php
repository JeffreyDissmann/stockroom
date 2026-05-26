<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use App\Services\ItemImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ItemGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_items_carry_their_thumbnails_for_the_card_carousel(): void
    {
        Storage::fake('public');

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill', 'parent_id' => null]);
        $processor = ItemImageProcessor::default();
        $processor->store($item, UploadedFile::fake()->image('a.jpg', 80, 80));
        $processor->store($item, UploadedFile::fake()->image('b.jpg', 80, 80));

        $this->actingAs(User::factory()->create())
            ->get('/items')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('items/Index')
                ->where('items.0.name', 'Drill')
                ->has('items.0.image_thumbs', 2)
                ->whereNot('items.0.thumb_url', null));
    }
}
