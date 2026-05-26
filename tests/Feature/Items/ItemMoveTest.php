<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_can_be_moved_to_a_new_parent(): void
    {
        $user = User::factory()->create();
        $garage = Item::factory()->room()->create();
        $kitchen = Item::factory()->room()->create();
        $toolbox = Item::factory()->container()->create(['parent_id' => $garage->id]);

        $this->actingAs($user)
            ->patch("/items/{$toolbox->id}/move", ['parent_id' => $kitchen->id])
            ->assertRedirect();

        $this->assertSame($kitchen->id, $toolbox->fresh()->parent_id);
    }

    public function test_descendant_ids_returns_the_full_subtree(): void
    {
        $garage = Item::factory()->room()->create();
        $shelf = Item::factory()->container()->create(['parent_id' => $garage->id]);
        $box = Item::factory()->container()->create(['parent_id' => $shelf->id]);
        $drill = Item::factory()->create(['parent_id' => $box->id]);
        $unrelated = Item::factory()->room()->create();

        $this->assertEqualsCanonicalizing([$shelf->id, $box->id, $drill->id], $garage->descendantIds());
        $this->assertSame([], $drill->descendantIds());
        $this->assertNotContains($unrelated->id, $garage->descendantIds());
    }

    public function test_move_targets_exclude_self_and_descendants(): void
    {
        $user = User::factory()->create();
        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        $kitchen = Item::factory()->room()->create(['name' => 'Kitchen']);
        $shelf = Item::factory()->container()->create(['name' => 'Shelf', 'parent_id' => $garage->id]);
        Item::factory()->container()->create(['name' => 'Box', 'parent_id' => $shelf->id]);

        // Destinations for Garage must exclude Garage (self) and Shelf + Box
        // (descendants), leaving only Kitchen.
        $this->actingAs($user)
            ->getJson("/items/{$garage->id}/move-targets")
            ->assertOk()
            ->assertJsonPath('targets', fn (array $targets) => collect($targets)->pluck('id')->all() === [$kitchen->id]);
    }

    public function test_move_targets_search_matches_locations_only(): void
    {
        $user = User::factory()->create();
        $kitchen = Item::factory()->room()->create(['name' => 'Kitchen']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Kitchen Scale']); // plain item, never a destination
        $mover = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Spatula']);

        $this->actingAs($user)
            ->getJson("/items/{$mover->id}/move-targets?q=Kitchen")
            ->assertOk()
            ->assertJsonPath('targets', fn (array $targets) => collect($targets)->pluck('id')->all() === [$kitchen->id]);
    }

    public function test_move_targets_can_include_plain_items_with_the_all_flag(): void
    {
        $user = User::factory()->create();
        $laptop = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Laptop']);
        $charger = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Charger']);

        // By default a plain item is not a destination.
        $this->actingAs($user)
            ->getJson("/items/{$charger->id}/move-targets?q=Laptop")
            ->assertJsonPath('targets', []);

        // With all=1, any item is eligible.
        $this->actingAs($user)
            ->getJson("/items/{$charger->id}/move-targets?q=Laptop&all=1")
            ->assertJsonPath('targets', fn (array $targets) => collect($targets)->pluck('id')->all() === [$laptop->id]);
    }

    public function test_item_can_be_moved_to_top_level(): void
    {
        $user = User::factory()->create();
        $garage = Item::factory()->room()->create();
        $toolbox = Item::factory()->container()->create(['parent_id' => $garage->id]);

        $this->actingAs($user)
            ->patch("/items/{$toolbox->id}/move", ['parent_id' => null])
            ->assertRedirect();

        $this->assertNull($toolbox->fresh()->parent_id);
    }

    public function test_cannot_move_item_into_itself(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $this->actingAs($user)
            ->from('/items')
            ->patch("/items/{$item->id}/move", ['parent_id' => $item->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_cannot_move_item_into_one_of_its_descendants(): void
    {
        $user = User::factory()->create();
        $garage = Item::factory()->room()->create();
        $shelf = Item::factory()->container()->create(['parent_id' => $garage->id]);
        $toolbox = Item::factory()->container()->create(['parent_id' => $shelf->id]);

        $this->actingAs($user)
            ->from('/items')
            ->patch("/items/{$garage->id}/move", ['parent_id' => $toolbox->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($garage->fresh()->parent_id);
    }
}
