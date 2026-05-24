<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'type' => ItemType::Item,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function room(): static
    {
        return $this->state(fn () => ['type' => ItemType::Room]);
    }

    public function container(): static
    {
        return $this->state(fn () => ['type' => ItemType::Container]);
    }
}
