<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_only_token_can_read(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/items')->assertOk();
    }

    public function test_read_only_token_cannot_create_items(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->postJson('/api/v1/items', ['name' => 'X', 'type' => 'item'])
            ->assertForbidden();
    }

    public function test_read_only_token_cannot_update_items(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->patchJson("/api/v1/items/{$item->id}", ['quantity' => 9])
            ->assertForbidden();
    }

    public function test_read_only_token_cannot_set_ha_link(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->putJson("/api/v1/items/{$item->id}/home-assistant-link", ['ha_entity_id' => 'sensor.x'])
            ->assertForbidden();
    }

    public function test_write_token_can_create_items(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);

        $this->postJson('/api/v1/items', ['name' => 'Allowed', 'type' => 'item'])
            ->assertCreated();
    }
}
