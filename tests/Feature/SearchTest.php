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
        $field = CustomField::factory()->searchable()->create(['name' => 'Voltage', 'type' => CustomFieldType::Text]);
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
        $field = CustomField::factory()->create(['name' => 'Private notes', 'type' => CustomFieldType::Text]);
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

    public function test_search_page_filters_by_paperless_document(): void
    {
        $user = User::factory()->create();

        // Two items linked to doc 547, one linked to a different doc, one
        // not linked at all. ?paperless_document=547 should return only
        // the first two.
        $linkedA = Item::factory()->create(['type' => ItemType::Item, 'name' => 'NUK Adapter']);
        $linkedB = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Flaschenwärmer']);
        $otherDoc = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Other doc item']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Unrelated']);

        $linkedA->paperlessLinks()->create(['paperless_document_id' => 547]);
        $linkedB->paperlessLinks()->create(['paperless_document_id' => 547]);
        $otherDoc->paperlessLinks()->create(['paperless_document_id' => 999]);

        $this->actingAs($user)->get('/search?paperless_document=547')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Search')
                ->where('filters.paperless_document', 547)
                ->has('items.data', 2));
    }

    public function test_search_page_filter_chip_round_trips_invalid_ids_as_null(): void
    {
        Item::factory()->count(2)->create(['type' => ItemType::Item]);

        $this->actingAs(User::factory()->create())
            ->get('/search?paperless_document=abc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.paperless_document', null)
                ->has('items.data', 2));
    }

    public function test_search_page_can_sort_by_recently_added(): void
    {
        $oldest = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Oldest']);
        $newest = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Newest']);
        $middle = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Middle']);

        $oldest->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $middle->forceFill(['created_at' => now()->subDay()])->saveQuietly();
        $newest->forceFill(['created_at' => now()])->saveQuietly();

        $this->actingAs(User::factory()->create())
            ->get('/search?sort=added')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Search')
                ->where('filters.sort', 'added')
                ->where('items.data.0.name', 'Newest')
                ->where('items.data.1.name', 'Middle')
                ->where('items.data.2.name', 'Oldest'));
    }

    public function test_search_page_can_sort_by_contents_count(): void
    {
        $empty = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Empty']);
        $full = Item::factory()->create(['type' => ItemType::Container, 'name' => 'Full']);
        Item::factory()->count(3)->create(['parent_id' => $full->id]);
        Item::factory()->create(['parent_id' => $empty->id]); // 1 child

        // Default direction is fullest-first.
        $this->actingAs(User::factory()->create())
            ->get('/search?sort=count')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sort', 'count')
                ->where('items.data.0.name', 'Full'));

        // Ascending flips it.
        $this->actingAs(User::factory()->create())
            ->get('/search?sort=count&dir=asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.dir', 'asc')
                ->where('items.data.0.children_count', 0));
    }

    public function test_search_page_can_sort_by_location(): void
    {
        $aRoom = Item::factory()->create(['type' => ItemType::Room, 'name' => 'Attic']);
        $zRoom = Item::factory()->create(['type' => ItemType::Room, 'name' => 'Zellar']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'In Zellar', 'parent_id' => $zRoom->id]);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'In Attic', 'parent_id' => $aRoom->id]);

        // Ascending by parent (room) name: Attic's item before Zellar's. The
        // rooms themselves have no parent and sort first.
        $this->actingAs(User::factory()->create())
            ->get('/search?sort=location&dir=asc&type=item')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.sort', 'location')
                ->where('items.data.0.name', 'In Attic')
                ->where('items.data.1.name', 'In Zellar'));
    }

    public function test_search_results_include_the_location_path(): void
    {
        $room = Item::factory()->create(['type' => ItemType::Room, 'name' => 'Garage']);
        $box = Item::factory()->create(['type' => ItemType::Container, 'name' => 'Toolbox', 'parent_id' => $room->id]);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Wrench', 'parent_id' => $box->id]);

        $this->actingAs(User::factory()->create())
            ->get('/search?sort=name')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.data', fn ($items) => collect($items)->firstWhere('name', 'Wrench')['location_path'] === 'Garage / Toolbox'));
    }
}
