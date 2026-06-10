<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatteryReading>
 */
class BatteryReadingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cycle = BatteryCycle::factory();

        return [
            'battery_cycle_id' => $cycle,
            // Keep the reading's item in sync with its cycle's item.
            'item_id' => fn (array $attrs) => BatteryCycle::find($attrs['battery_cycle_id'])?->item_id
                ?? Item::factory(),
            'percent' => fake()->numberBetween(1, 100),
            'recorded_at' => now(),
        ];
    }

    /**
     * Attach the reading to an existing cycle (and its item).
     */
    public function forCycle(BatteryCycle $cycle): static
    {
        return $this->state(fn (): array => [
            'battery_cycle_id' => $cycle->id,
            'item_id' => $cycle->item_id,
        ]);
    }
}
