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

class ApiHomeAssistantLinkTest extends TestCase
{
    use RefreshDatabase;

    private function actAsWriter(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);
    }

    public function test_put_creates_the_link(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsWriter();

        $this->putJson("/api/v1/items/{$item->id}/home-assistant-link", [
            'ha_entity_id' => 'sensor.drill_battery',
            'ha_device_id' => 'abc123',
            'friendly_name' => 'Drill',
            'url' => 'http://homeassistant.local:8123/config/devices/device/abc123',
        ])
            // First PUT creates the link → 201 (resource auto-201 on a freshly
            // created model). A subsequent PUT that replaces it returns 200.
            ->assertCreated()
            ->assertJsonPath('data.ha_entity_id', 'sensor.drill_battery')
            ->assertJsonPath('data.friendly_name', 'Drill');

        $this->assertDatabaseHas('home_assistant_links', [
            'item_id' => $item->id,
            'ha_entity_id' => 'sensor.drill_battery',
        ]);
    }

    public function test_put_replaces_the_existing_link_one_to_one(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $item->id, 'ha_entity_id' => 'sensor.old']);
        $this->actAsWriter();

        $this->putJson("/api/v1/items/{$item->id}/home-assistant-link", [
            'ha_entity_id' => 'sensor.new',
        ])->assertOk()->assertJsonPath('data.ha_entity_id', 'sensor.new');

        // Still exactly one link for the item, now pointing at the new entity.
        $this->assertSame(1, HomeAssistantLink::query()->where('item_id', $item->id)->count());
        $this->assertDatabaseHas('home_assistant_links', ['item_id' => $item->id, 'ha_entity_id' => 'sensor.new']);
    }

    public function test_put_validates_entity_id_required(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsWriter();

        $this->putJson("/api/v1/items/{$item->id}/home-assistant-link", ['friendly_name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ha_entity_id');
    }

    public function test_delete_removes_the_link(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $item->id]);
        $this->actAsWriter();

        $this->deleteJson("/api/v1/items/{$item->id}/home-assistant-link")
            ->assertNoContent();

        $this->assertDatabaseMissing('home_assistant_links', ['item_id' => $item->id]);
    }

    public function test_link_surfaces_in_item_show(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $item->id, 'ha_entity_id' => 'light.lamp']);

        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson("/api/v1/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.home_assistant_link.ha_entity_id', 'light.lamp');
    }
}
