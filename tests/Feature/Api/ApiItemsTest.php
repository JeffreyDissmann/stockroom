<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ItemType;
use App\Models\HomeAssistantLink;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiItemsTest extends TestCase
{
    use RefreshDatabase;

    private function actAsReader(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/items')->assertUnauthorized();
    }

    public function test_index_returns_paginated_summary(): void
    {
        Item::factory()->count(3)->create(['type' => ItemType::Item]);
        $this->actAsReader();

        $this->getJson('/api/v1/items')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'type' => ['value', 'label'], 'location_path', 'quantity', 'has_ha_link']],
                'meta' => ['current_page', 'total'],
            ]);
    }

    public function test_index_filters_by_type(): void
    {
        Item::factory()->room()->create();
        Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsReader();

        $this->getJson('/api/v1/items?type=room')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type.value', 'room');
    }

    public function test_index_filters_by_direct_parent(): void
    {
        $room = Item::factory()->room()->create();
        $child = Item::factory()->create(['type' => ItemType::Item, 'parent_id' => $room->id]);
        Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsReader();

        $this->getJson("/api/v1/items?parent={$room->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $child->id);
    }

    public function test_index_filters_by_room_subtree(): void
    {
        $room = Item::factory()->room()->create();
        $box = Item::factory()->container()->create(['parent_id' => $room->id]);
        $deep = Item::factory()->create(['type' => ItemType::Item, 'parent_id' => $box->id]);
        Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsReader();

        // Subtree includes the nested container AND the item two levels down.
        $this->getJson("/api/v1/items?room={$room->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $box->id])
            ->assertJsonFragment(['id' => $deep->id]);
    }

    public function test_index_filters_by_tag(): void
    {
        $tag = Tag::factory()->create();
        $tagged = Item::factory()->create(['type' => ItemType::Item]);
        $tagged->tags()->attach($tag);
        Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsReader();

        $this->getJson("/api/v1/items?tag={$tag->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tagged->id);
    }

    public function test_index_filters_by_ha_link_presence(): void
    {
        $linked = Item::factory()->create(['type' => ItemType::Item]);
        HomeAssistantLink::factory()->create(['item_id' => $linked->id]);
        $unlinked = Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsReader();

        $this->getJson('/api/v1/items?has_ha_link=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->id);

        $this->getJson('/api/v1/items?has_ha_link=0')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unlinked->id);
    }

    public function test_show_returns_full_detail_with_ha_link(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Garage']);
        $item = Item::factory()->create([
            'type' => ItemType::Item,
            'name' => 'Cordless Drill',
            'parent_id' => $room->id,
            'manufacturer' => 'Bosch',
        ]);
        $item->tags()->attach(Tag::factory()->create(['name' => 'Powertools']));
        HomeAssistantLink::factory()->create([
            'item_id' => $item->id,
            'ha_entity_id' => 'sensor.drill_battery',
        ]);
        $this->actAsReader();

        $this->getJson("/api/v1/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Cordless Drill')
            ->assertJsonPath('data.location_path', 'Garage')
            ->assertJsonPath('data.manufacturer', 'Bosch')
            ->assertJsonPath('data.tags.0.name', 'Powertools')
            ->assertJsonPath('data.home_assistant_link.ha_entity_id', 'sensor.drill_battery');
    }

    public function test_show_returns_null_ha_link_when_unlinked(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $this->actAsReader();

        $this->getJson("/api/v1/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.home_assistant_link', null);
    }
}
