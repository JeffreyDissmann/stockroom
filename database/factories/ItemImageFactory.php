<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemImage>
 *
 * Writes only the database row — no file is placed on disk. Tests that need
 * real bytes upload through the controller instead; this exists so batch jobs
 * and queries can be tested without touching the filesystem.
 */
class ItemImageFactory extends Factory
{
    protected $model = ItemImage::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'width_original' => 1600,
            'height_original' => 1200,
            'size_bytes_original' => 480_000,
            'sort_order' => 0,
            'is_primary' => false,
            'analyzed_at' => null,
        ];
    }

    /** Already seen by the review agent, so batch runs should skip it. */
    public function analyzed(): static
    {
        return $this->state(fn (): array => ['analyzed_at' => now()]);
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }
}
