<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BatteryCycle;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryCycle>
 */
class BatteryCycleFactory extends Factory
{
    /**
     * Default: an open (current) cycle installed a month ago.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'installed_at' => now()->subMonth(),
            'removed_at' => null,
            'notes' => null,
        ];
    }

    /**
     * A closed (historical) cycle, removed $days ago after running $life days.
     */
    public function closed(int $life = 90, int $removedDaysAgo = 10): static
    {
        return $this->state(fn (): array => [
            'installed_at' => now()->subDays($removedDaysAgo + $life),
            'removed_at' => now()->subDays($removedDaysAgo),
        ]);
    }
}
