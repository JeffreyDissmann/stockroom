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
use Inertia\Testing\AssertableInertia;
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

    public function test_ignores_values_from_non_searchable_custom_fields(): void
    {
        $field = CustomField::factory()->notSearchable()->create(['name' => 'Private notes', 'type' => CustomFieldType::Text]);
        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Toolbox']);
        $item->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'secretphrase']);

        $this->actingAs(User::factory()->create())
            ->getJson('/search?q=secretphrase')
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_search_page_renders_matching_items(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Cordless Drill', 'parent_id' => $room->id]);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Hammer']);

        $this->actingAs(User::factory()->create())
            ->get('/search?q=drill')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Search')
                ->where('query', 'drill')
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Cordless Drill'));
    }

    public function test_search_page_filters_by_type(): void
    {
        Item::factory()->room()->create(['name' => 'Workshop']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Workshop drill']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/search?q=Workshop')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items.data', 2));

        $this->actingAs($user)->get('/search?q=Workshop&type=item')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Workshop drill'));
    }

    public function test_search_page_filters_by_tag(): void
    {
        $tag = Tag::factory()->create(['name' => 'Outdoor']);
        $hose = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Garden hose']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Garden gnome']);
        $hose->tags()->attach($tag);

        $this->actingAs(User::factory()->create())
            ->get("/search?q=Garden&tags[]={$tag->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Garden hose'));
    }

    public function test_search_page_filters_by_multiple_tags_matching_any(): void
    {
        $outdoor = Tag::factory()->create(['name' => 'Outdoor']);
        $fragile = Tag::factory()->create(['name' => 'Fragile']);

        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Garden hose'])->tags()->attach($outdoor);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Garden vase'])->tags()->attach($fragile);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Garden gnome']);

        $this->actingAs(User::factory()->create())
            ->get("/search?q=Garden&tags[]={$outdoor->id}&tags[]={$fragile->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('items.data', 2));
    }

    public function test_search_page_browses_all_without_a_query(): void
    {
        Item::factory()->count(3)->create(['type' => ItemType::Item]);

        $this->actingAs(User::factory()->create())
            ->get('/search')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Search')
                ->where('query', '')
                ->has('items.data', 3));
    }
}
