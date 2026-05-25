<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Enums\ItemType;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_reset_requires_authentication(): void
    {
        $this->post('/household/reset')->assertRedirect('/login');
    }

    public function test_wipe_deletes_the_inventory_but_keeps_tags_by_default(): void
    {
        $tag = Tag::factory()->create();
        $field = CustomField::factory()->create();

        $room = Item::factory()->room()->create();
        $item = Item::factory()->create(['type' => ItemType::Item, 'parent_id' => $room->id]);
        $item->tags()->attach($tag);
        $item->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'x']);
        ItemImageProcessor::default()->store($item, UploadedFile::fake()->image('p.jpg', 100, 100));
        $imageDir = $item->images()->firstOrFail()->directory();

        $this->actingAs(User::factory()->create())
            ->post('/household/reset', ['include_tags' => false])
            ->assertRedirect();

        $this->assertSame(0, Item::count());
        $this->assertDatabaseEmpty('item_images');
        $this->assertDatabaseEmpty('custom_field_values');
        $this->assertDatabaseEmpty('item_tag');
        Storage::disk('public')->assertMissing($imageDir);

        // Preserved: tags, custom field definitions, users.
        $this->assertModelExists($tag);
        $this->assertModelExists($field);
    }

    public function test_wipe_can_also_delete_tags(): void
    {
        Tag::factory()->count(2)->create();
        Item::factory()->create(['type' => ItemType::Item]);

        $this->actingAs(User::factory()->create())
            ->post('/household/reset', ['include_tags' => true])
            ->assertRedirect();

        $this->assertSame(0, Item::count());
        $this->assertSame(0, Tag::count());
    }

    public function test_wipe_keeps_custom_field_definitions_by_default(): void
    {
        CustomField::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->post('/household/reset', ['include_custom_fields' => false])
            ->assertRedirect();

        $this->assertSame(2, CustomField::count());
    }

    public function test_wipe_can_also_delete_custom_field_definitions(): void
    {
        CustomField::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->post('/household/reset', ['include_custom_fields' => true])
            ->assertRedirect();

        $this->assertSame(0, CustomField::count());
    }
}
