<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeAssistantTagTest extends TestCase
{
    use RefreshDatabase;

    private function linkViaApi(Item $item, string $entityId = 'sensor.x'): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);
        $this->putJson("/api/v1/items/{$item->id}/home-assistant-link", ['ha_entity_id' => $entityId])
            ->assertSuccessful();
    }

    public function test_linking_creates_assigns_and_selects_the_home_assistant_tag(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);

        $this->linkViaApi($item);

        $tag = Tag::query()->where('name', 'HomeAssistant')->sole();
        $this->assertTrue($item->fresh()->tags->contains($tag), 'item should carry the HomeAssistant tag');
        $this->assertSame($tag->id, Setting::int('home_assistant_tag_id'), 'tag should be recorded as selected');
    }

    public function test_uses_the_configured_tag_when_one_is_selected(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $custom = Tag::factory()->create(['name' => 'Smart Home']);
        Setting::set('home_assistant_tag_id', $custom->id);

        $this->linkViaApi($item);

        // The household's chosen tag is used; no default "HomeAssistant" tag
        // is created, and the setting is untouched.
        $this->assertTrue($item->fresh()->tags->contains($custom));
        $this->assertSame(0, Tag::query()->where('name', 'HomeAssistant')->count());
        $this->assertSame($custom->id, Setting::int('home_assistant_tag_id'));
    }

    public function test_relinking_does_not_duplicate_the_tag(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);

        $this->linkViaApi($item, 'sensor.a');
        $this->linkViaApi($item, 'sensor.b');

        $this->assertSame(1, Tag::query()->where('name', 'HomeAssistant')->count());
        $this->assertSame(1, $item->fresh()->tags()->where('name', 'HomeAssistant')->count());
    }

    public function test_api_unlink_removes_the_tag_from_the_item_but_keeps_the_tag(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $this->linkViaApi($item);
        $tagId = Setting::int('home_assistant_tag_id');

        Sanctum::actingAs(User::factory()->create(), ['read', 'write']);
        $this->deleteJson("/api/v1/items/{$item->id}/home-assistant-link")->assertNoContent();

        $this->assertFalse($item->fresh()->tags->contains('id', $tagId), 'tag should be detached from the item');
        $this->assertDatabaseHas('tags', ['id' => $tagId]); // the tag itself survives
        $this->assertSame($tagId, Setting::int('home_assistant_tag_id')); // still selected
    }

    public function test_web_unlink_also_removes_the_tag(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $this->linkViaApi($item);
        $tagId = Setting::int('home_assistant_tag_id');

        $this->actingAs(User::factory()->create())
            ->from("/items/{$item->id}")
            ->delete("/items/{$item->id}/home-assistant-link")
            ->assertRedirect("/items/{$item->id}");

        $this->assertFalse($item->fresh()->tags->contains('id', $tagId));
        $this->assertDatabaseHas('tags', ['id' => $tagId]);
    }

    public function test_does_not_disturb_other_tags(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $other = Tag::factory()->create(['name' => 'Powertools']);
        $item->tags()->attach($other);

        $this->linkViaApi($item);

        $names = $item->fresh()->tags->pluck('name');
        $this->assertTrue($names->contains('Powertools'));
        $this->assertTrue($names->contains('HomeAssistant'));
    }

    public function test_selected_home_assistant_tag_cannot_be_deleted(): void
    {
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $this->linkViaApi($item);
        $tagId = Setting::int('home_assistant_tag_id');

        // Tag deletion is admin-only.
        $this->actingAs(User::factory()->admin()->create())
            ->from('/tags')
            ->delete("/tags/{$tagId}")
            ->assertSessionHasErrors('tag');

        $this->assertDatabaseHas('tags', ['id' => $tagId]);
    }
}
