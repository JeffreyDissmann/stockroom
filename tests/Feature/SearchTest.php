<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CustomFieldType;
use App\Enums\ItemType;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_authentication(): void
    {
        $this->get('/search?q=drill')->assertRedirect('/login');
    }

    public function test_blank_query_returns_no_results(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/search?q=')
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_finds_items_by_name_and_includes_location_path(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Cordless Drill', 'parent_id' => $room->id]);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Hammer']);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/search?q=drill')
            ->assertOk()
            ->assertJsonCount(1, 'results');

        $response->assertJsonPath('results.0.name', 'Cordless Drill');
        $response->assertJsonPath('results.0.path', 'Garage');
        $response->assertJsonPath('results.0.type.value', 'item');
    }

    public function test_finds_items_by_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Powertools']);
        $saw = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Saw']);
        $saw->tags()->attach($tag);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Mug']);

        $this->actingAs(User::factory()->create())
            ->getJson('/search?q=Powertools')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Saw');
    }

    public function test_finds_items_by_custom_field_value(): void
    {
        $field = CustomField::factory()->create(['name' => 'Voltage', 'type' => CustomFieldType::Text]);
        $saw = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Angle grinder']);
        $saw->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'cordless18v']);

        $this->actingAs(User::factory()->create())
            ->getJson('/search?q=cordless18v')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.name', 'Angle grinder');
    }
}
