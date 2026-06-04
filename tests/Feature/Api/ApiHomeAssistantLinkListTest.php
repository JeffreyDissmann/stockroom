<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ItemType;
use App\Models\HomeAssistantLink;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiHomeAssistantLinkListTest extends TestCase
{
    use RefreshDatabase;

    private function actAsReader(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/home-assistant-links')->assertUnauthorized();
    }

    public function test_read_only_token_is_sufficient(): void
    {
        $this->actAsReader();
        $this->getJson('/api/v1/home-assistant-links')->assertOk();
    }

    public function test_returns_only_items_that_have_a_link(): void
    {
        $linked = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Cordless Drill']);
        HomeAssistantLink::factory()->create(['item_id' => $linked->id, 'ha_entity_id' => 'sensor.drill']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Unlinked']);

        $this->actAsReader();

        $this->getJson('/api/v1/home-assistant-links')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id)
            ->assertJsonPath('data.0.name', 'Cordless Drill');
    }

    public function test_embeds_the_full_home_assistant_link_object(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Garage']);
        $item = Item::factory()->create(['type' => ItemType::Item, 'parent_id' => $room->id]);
        HomeAssistantLink::factory()->create([
            'item_id' => $item->id,
            'ha_entity_id' => 'sensor.drill_battery',
            'ha_device_id' => '9f8c0a3b1d2e4f50',
            'friendly_name' => 'Cordless Drill',
            'instance_id' => 'inst-1',
        ]);

        $this->actAsReader();

        $this->getJson('/api/v1/home-assistant-links')
            ->assertOk()
            ->assertJsonPath('data.0.location_path', 'Garage')
            ->assertJsonPath('data.0.type.value', 'item')
            ->assertJsonPath('data.0.home_assistant_link.ha_entity_id', 'sensor.drill_battery')
            ->assertJsonPath('data.0.home_assistant_link.ha_device_id', '9f8c0a3b1d2e4f50')
            ->assertJsonPath('data.0.home_assistant_link.friendly_name', 'Cordless Drill')
            ->assertJsonPath('data.0.home_assistant_link.instance_id', 'inst-1')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'location_path', 'home_assistant_link' => [
                    'ha_entity_id', 'ha_device_id', 'friendly_name', 'url', 'instance_id', 'created_at', 'updated_at',
                ]]],
                'links',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);
    }

    public function test_filters_by_instance_id(): void
    {
        $a = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $a->id, 'instance_id' => 'home-1']);
        $b = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $b->id, 'instance_id' => 'home-2']);

        $this->actAsReader();

        $this->getJson('/api/v1/home-assistant-links?instance_id=home-1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $a->id);

        // No filter → both.
        $this->getJson('/api/v1/home-assistant-links')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_paginates_and_caps_per_page(): void
    {
        Item::factory()->count(3)->create(['type' => ItemType::Item])
            ->each(fn (Item $item) => HomeAssistantLink::factory()->create(['item_id' => $item->id]));

        $this->actAsReader();

        $this->getJson('/api/v1/home-assistant-links?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }
}
