<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_statistics_requires_authentication(): void
    {
        $this->getJson('/api/v1/statistics')->assertUnauthorized();
    }

    public function test_returns_counts_value_and_breakdowns(): void
    {
        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->container()->create(['parent_id' => $garage->id]);

        $tag = Tag::factory()->create(['name' => 'Powertools']);
        $drill = Item::factory()->create(['type' => ItemType::Item, 'parent_id' => $garage->id, 'purchase_price' => 100]);
        $drill->tags()->attach($tag);

        // A sold item is excluded from the owned value.
        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 50, 'sold_date' => now()]);

        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/statistics')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonPath('value', 100)
            ->assertJsonPath('by_type.room', 1)
            ->assertJsonPath('by_type.container', 1)
            ->assertJsonPath('by_type.item', 2)
            ->assertJsonPath('by_tag.0.name', 'Powertools')
            ->assertJsonPath('by_tag.0.items_count', 1)
            ->assertJsonPath('by_room.0.name', 'Garage')
            ->assertJsonPath('by_room.0.children_count', 2);
    }
}
