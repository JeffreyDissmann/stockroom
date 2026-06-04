<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/search?q=drill')->assertUnauthorized();
    }

    public function test_blank_query_returns_no_results(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/search?q=')
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_finds_items_and_returns_location_path(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Cordless Drill', 'parent_id' => $room->id]);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Hammer']);

        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/search?q=drill')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Cordless Drill')
            ->assertJsonPath('results.0.path', 'Garage')
            ->assertJsonPath('results.0.type.value', 'item');
    }
}
