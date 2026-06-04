<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemType;
use App\Models\HomeAssistantLink;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HomeAssistantLinkDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_passes_the_home_assistant_link_when_present(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create([
            'item_id' => $item->id,
            'ha_entity_id' => 'sensor.drill_battery',
            'friendly_name' => 'Drill',
            'url' => 'http://homeassistant.local:8123/config/devices/device/abc',
        ]);

        $this->actingAs(User::factory()->create())
            ->get("/items/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('items/Show')
                ->where('homeAssistantLink.entity_id', 'sensor.drill_battery')
                ->where('homeAssistantLink.friendly_name', 'Drill')
                ->where('homeAssistantLink.url', 'http://homeassistant.local:8123/config/devices/device/abc'));
    }

    public function test_show_passes_null_when_not_linked(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);

        $this->actingAs(User::factory()->create())
            ->get("/items/{$item->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('items/Show')
                ->where('homeAssistantLink', null));
    }

    public function test_edit_passes_the_home_assistant_link_for_unlinking(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $item->id, 'ha_entity_id' => 'light.lamp']);

        $this->actingAs(User::factory()->create())
            ->get("/items/{$item->id}/edit")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('items/Edit')
                ->where('homeAssistantLink.entity_id', 'light.lamp'));
    }

    public function test_unlink_removes_the_link(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $item->id]);

        $this->actingAs(User::factory()->create())
            ->from("/items/{$item->id}")
            ->delete("/items/{$item->id}/home-assistant-link")
            ->assertRedirect("/items/{$item->id}");

        $this->assertDatabaseMissing('home_assistant_links', ['item_id' => $item->id]);
    }

    public function test_unlink_requires_authentication(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $item->id]);

        $this->delete("/items/{$item->id}/home-assistant-link")->assertRedirect('/login');

        $this->assertDatabaseHas('home_assistant_links', ['item_id' => $item->id]);
    }
}
