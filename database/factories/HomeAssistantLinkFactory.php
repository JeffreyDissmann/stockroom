<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\HomeAssistantLink;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomeAssistantLink>
 */
class HomeAssistantLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entityId = $this->faker->randomElement(['sensor', 'light', 'switch', 'binary_sensor'])
            .'.'.$this->faker->unique()->slug(2, false);

        return [
            'item_id' => Item::factory()->state(['type' => ItemType::Item]),
            'ha_entity_id' => $entityId,
            'ha_device_id' => $this->faker->uuid(),
            'friendly_name' => $this->faker->words(2, true),
            'url' => 'http://homeassistant.local:8123/config/devices/device/'.$this->faker->uuid(),
            'instance_id' => null,
        ];
    }
}
