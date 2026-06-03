<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRoomsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rooms_require_authentication(): void
    {
        $this->getJson('/api/v1/rooms')->assertUnauthorized();
    }

    public function test_lists_only_rooms_with_child_counts(): void
    {
        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->container()->create(['parent_id' => $garage->id]);
        Item::factory()->room()->create(['name' => 'Attic']);
        Item::factory()->create(['type' => ItemType::Item]);

        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/rooms')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Attic')
            ->assertJsonPath('data.0.children_count', 0)
            ->assertJsonPath('data.1.name', 'Garage')
            ->assertJsonPath('data.1.children_count', 1);
    }
}
