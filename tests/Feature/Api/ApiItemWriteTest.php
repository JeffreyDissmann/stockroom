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

class ApiItemWriteTest extends TestCase
{
    use RefreshDatabase;

    private function actAsWriter(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);
    }

    public function test_store_creates_an_item_and_indexes_it(): void
    {
        $room = Item::factory()->room()->create();
        $tag = Tag::factory()->create();
        $this->actAsWriter();

        $response = $this->postJson('/api/v1/items', [
            'name' => 'Robot Vacuum',
            'type' => 'item',
            'parent_id' => $room->id,
            'manufacturer' => 'iRobot',
            'tags' => [$tag->id],
        ])->assertCreated();

        $response->assertJsonPath('data.name', 'Robot Vacuum')
            ->assertJsonPath('data.manufacturer', 'iRobot')
            ->assertJsonPath('data.tags.0.id', $tag->id);

        $item = Item::query()->where('name', 'Robot Vacuum')->sole();
        $this->assertSame($room->id, $item->parent_id);
        $this->assertTrue($item->tags->contains($tag));
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actAsWriter();

        $this->postJson('/api/v1/items', ['type' => 'item'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_update_applies_partial_changes_without_touching_tags(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Old', 'quantity' => 1]);
        $tag = Tag::factory()->create();
        $item->tags()->attach($tag);
        $this->actAsWriter();

        // PATCH only quantity — name and tags should be untouched.
        $this->patchJson("/api/v1/items/{$item->id}", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.name', 'Old');

        $item->refresh();
        $this->assertSame(5, $item->quantity);
        $this->assertTrue($item->tags->contains($tag));
    }

    public function test_update_replaces_tags_when_key_present(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $old = Tag::factory()->create();
        $new = Tag::factory()->create();
        $item->tags()->attach($old);
        $this->actAsWriter();

        $this->patchJson("/api/v1/items/{$item->id}", ['tags' => [$new->id]])
            ->assertOk();

        $item->refresh();
        $this->assertFalse($item->tags->contains($old));
        $this->assertTrue($item->tags->contains($new));
    }
}
