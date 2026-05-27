<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\AssignTags;
use App\Ai\Tools\CreateItem;
use App\Ai\Tools\DeleteItem;
use App\Ai\Tools\GetItem;
use App\Ai\Tools\InventoryStats;
use App\Ai\Tools\MoveItem;
use App\Ai\Tools\SearchItems;
use App\Ai\Tools\UpdateItem;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_items_finds_matches(): void
    {
        Item::factory()->create(['name' => 'Cordless Drill', 'type' => ItemType::Item]);
        Item::factory()->create(['name' => 'Lawn Mower', 'type' => ItemType::Item]);

        $result = app(SearchItems::class)->handle(new Request(['query' => 'drill']));

        $this->assertStringContainsString('Cordless Drill', $result);
        $this->assertStringNotContainsString('Lawn Mower', $result);
    }

    public function test_get_item_returns_details(): void
    {
        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        $item = Item::factory()->create(['name' => 'Drill', 'type' => ItemType::Item, 'parent_id' => $garage->id, 'manufacturer' => 'DeWalt']);

        $result = (new GetItem)->handle(new Request(['id' => $item->id]));

        $this->assertStringContainsString('Drill', $result);
        $this->assertStringContainsString('Garage', $result);
        $this->assertStringContainsString('DeWalt', $result);
    }

    public function test_inventory_stats_counts_and_sums(): void
    {
        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 100]);
        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 50]);
        Item::factory()->room()->create();

        $this->assertStringContainsString('3', (new InventoryStats)->handle(new Request(['metric' => 'count'])));
        $this->assertStringContainsString('150', (new InventoryStats)->handle(new Request(['metric' => 'value'])));
    }

    public function test_inventory_stats_filters_by_type_and_labels_currency(): void
    {
        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 100]);
        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 50]);
        Item::factory()->room()->create();

        // type=item excludes the room from the count (2, not 3).
        $count = (new InventoryStats)->handle(new Request(['metric' => 'count', 'type' => 'item']));
        $this->assertStringContainsString('Total items: 2', $count);

        // Value output carries the household currency code so the model never guesses the symbol.
        $value = (new InventoryStats)->handle(new Request(['metric' => 'value', 'type' => 'item']));
        $this->assertStringContainsString('150', $value);
        $this->assertStringContainsString((string) config('stockroom.currency.code'), $value);
    }

    public function test_inventory_stats_groups_by_tag(): void
    {
        $tools = Tag::factory()->create(['name' => 'Tools']);
        $idle = Tag::factory()->create(['name' => 'Idle']); // no items — must not appear

        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 100])->tags()->attach($tools);
        Item::factory()->create(['type' => ItemType::Item, 'purchase_price' => 40])->tags()->attach($tools);

        $count = (new InventoryStats)->handle(new Request(['metric' => 'count', 'group_by' => 'tag']));
        $this->assertStringContainsString('Tools: 2', $count);
        $this->assertStringNotContainsString('Idle', $count); // tags with no matching items are omitted

        $value = (new InventoryStats)->handle(new Request(['metric' => 'value', 'group_by' => 'tag']));
        $this->assertStringContainsString('140', $value);
        $this->assertStringContainsString((string) config('stockroom.currency.code'), $value);
    }

    public function test_create_item_creates(): void
    {
        $result = app(CreateItem::class)->handle(new Request(['name' => 'Hammer', 'type' => 'item']));

        $this->assertDatabaseHas('items', ['name' => 'Hammer', 'type' => 'item']);
        $this->assertStringContainsString('Hammer', $result);
    }

    public function test_create_item_rejects_bad_type(): void
    {
        $result = app(CreateItem::class)->handle(new Request(['name' => 'X', 'type' => 'gadget']));

        $this->assertStringContainsString('valid type', $result);
        $this->assertDatabaseMissing('items', ['name' => 'X']);
    }

    public function test_update_item_changes_only_given_fields(): void
    {
        $item = Item::factory()->create(['name' => 'Old', 'type' => ItemType::Item, 'quantity' => 5]);

        app(UpdateItem::class)->handle(new Request(['id' => $item->id, 'name' => 'New']));

        $item->refresh();
        $this->assertSame('New', $item->name);
        $this->assertSame(5, $item->quantity); // untouched
    }

    public function test_move_item_moves_and_blocks_cycles(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Shed']);
        $item = Item::factory()->create(['type' => ItemType::Item]);

        app(MoveItem::class)->handle(new Request(['id' => $item->id, 'parent_id' => $room->id]));
        $this->assertSame($room->id, $item->fresh()->parent_id);

        $child = Item::factory()->container()->create(['parent_id' => $room->id]);
        $result = app(MoveItem::class)->handle(new Request(['id' => $room->id, 'parent_id' => $child->id]));
        $this->assertStringContainsString('Cannot move', $result);
    }

    public function test_assign_tags_attaches_existing_only(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        Tag::factory()->create(['name' => 'Tools']);

        $result = app(AssignTags::class)->handle(new Request(['id' => $item->id, 'tags' => 'Tools, Nonexistent']));

        $this->assertTrue($item->fresh()->tags->contains(fn (Tag $t): bool => $t->name === 'Tools'));
        $this->assertStringContainsString('Nonexistent', $result); // reported as unknown
        $this->assertDatabaseMissing('tags', ['name' => 'Nonexistent']);
    }

    public function test_delete_item_deletes(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Gone']);

        app(DeleteItem::class)->handle(new Request(['id' => $item->id]));

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    }
}
