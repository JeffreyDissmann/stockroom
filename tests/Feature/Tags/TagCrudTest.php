<?php

declare(strict_types=1);

namespace Tests\Feature\Tags;

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_auto_generates_slug(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->post('/tags', [
            'name' => 'Power Tools',
            'color' => '#ff8800',
        ])->assertRedirect('/tags');

        $tag = Tag::where('name', 'Power Tools')->firstOrFail();
        $this->assertSame('power-tools', $tag->slug);
        $this->assertSame('#ff8800', $tag->color);
    }

    public function test_duplicate_name_is_rejected(): void
    {
        $user = User::factory()->admin()->create();
        Tag::factory()->create(['name' => 'Tools']);

        $this->actingAs($user)
            ->from('/tags')
            ->post('/tags', ['name' => 'Tools'])
            ->assertSessionHasErrors('name');
    }

    public function test_invalid_color_is_rejected(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->from('/tags')
            ->post('/tags', ['name' => 'Bad', 'color' => 'red'])
            ->assertSessionHasErrors('color');
    }

    public function test_destroy_removes_pivot_rows(): void
    {
        $user = User::factory()->admin()->create();
        $tag = Tag::factory()->create();
        $item = Item::factory()->create();
        $item->tags()->attach($tag);

        $this->actingAs($user)->delete("/tags/{$tag->id}")->assertRedirect('/tags');

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseMissing('item_tag', ['item_id' => $item->id, 'tag_id' => $tag->id]);
    }
}
