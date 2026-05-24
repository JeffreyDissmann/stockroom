<?php

namespace Tests\Feature\Items;

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
