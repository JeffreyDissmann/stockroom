<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_top_level_items_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->room()->create(['name' => 'Kitchen']);
        $nested = Item::factory()->create(['name' => 'Hidden', 'parent_id' => Item::first()->id]);

        $response = $this->actingAs($user)->get('/items');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('items/Index')
            ->where('items.0.name', 'Garage')
            ->where('items.1.name', 'Kitchen')
            ->has('items', 2)
        );
    }

    public function test_index_drilldown_returns_children(): void
    {
        $user = User::factory()->create();
        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->container()->create(['name' => 'Shelf A', 'parent_id' => $garage->id]);

        $response = $this->actingAs($user)->get('/items?parent='.$garage->id);

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('parent.name', 'Garage')
            ->where('items.0.name', 'Shelf A')
            ->has('items', 1)
        );
    }

    public function test_store_creates_item_with_parent_and_tags(): void
    {
        $user = User::factory()->create();
        $garage = Item::factory()->room()->create();
        $tag = Tag::factory()->create();

        $response = $this->actingAs($user)->post('/items', [
            'name' => 'Toolbox',
            'description' => 'Red toolbox',
            'type' => ItemType::Container->value,
            'parent_id' => $garage->id,
            'tags' => [$tag->id],
        ]);

        $item = Item::where('name', 'Toolbox')->firstOrFail();
        $response->assertRedirect("/items/{$item->id}");

        $this->assertSame($garage->id, $item->parent_id);
        $this->assertSame(ItemType::Container, $item->type);
        $this->assertTrue($item->tags->contains($tag));
    }

    public function test_store_persists_room_icon(): void
    {
        $this->actingAs(User::factory()->create())->post('/items', [
            'name' => 'Kitchen',
            'type' => ItemType::Room->value,
            'icon' => 'utensils',
        ])->assertRedirect();

        $this->assertSame('utensils', Item::where('name', 'Kitchen')->firstOrFail()->icon);
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get('/items')->assertRedirect('/login');
    }

    public function test_destroy_promotes_children_to_top_level(): void
    {
        $user = User::factory()->create();
        $parent = Item::factory()->room()->create();
        $child = Item::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($user)->delete("/items/{$parent->id}")->assertRedirect('/items');

        $this->assertDatabaseMissing('items', ['id' => $parent->id]);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_destroy_blocks_the_room_set_as_paperless_intake_parent(): void
    {
        $user = User::factory()->create();
        $room = Item::factory()->room()->create();
        Setting::set('paperless_parent_id', $room->id);

        $this->actingAs($user)
            ->from("/items/{$room->id}")
            ->delete("/items/{$room->id}")
            ->assertSessionHasErrors('item');

        $this->assertDatabaseHas('items', ['id' => $room->id]);
    }
}
