<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Enums\CustomFieldType;
use App\Enums\ItemType;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ItemCustomFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_can_be_created_with_custom_field_values(): void
    {
        $color = CustomField::factory()->create(['name' => 'Color', 'type' => CustomFieldType::Text]);
        $watts = CustomField::factory()->create(['name' => 'Watts', 'type' => CustomFieldType::Number]);

        $this->actingAs(User::factory()->create())->post('/items', [
            'type' => 'item',
            'name' => 'Lamp',
            'custom_fields' => [$color->id => 'Black', $watts->id => '60'],
        ])->assertRedirect();

        $item = Item::where('name', 'Lamp')->firstOrFail();
        $this->assertSame('Black', $item->customFieldValues()->where('custom_field_id', $color->id)->value('value'));
        $this->assertSame('60', $item->customFieldValues()->where('custom_field_id', $watts->id)->value('value'));
    }

    public function test_number_field_rejects_non_numeric(): void
    {
        $watts = CustomField::factory()->create(['type' => CustomFieldType::Number]);

        $this->actingAs(User::factory()->create())->post('/items', [
            'type' => 'item',
            'name' => 'Lamp',
            'custom_fields' => [$watts->id => 'abc'],
        ])->assertSessionHasErrors("custom_fields.{$watts->id}");
    }

    public function test_url_field_rejects_invalid_url(): void
    {
        $link = CustomField::factory()->create(['type' => CustomFieldType::Url]);

        $this->actingAs(User::factory()->create())->post('/items', [
            'type' => 'item',
            'name' => 'Lamp',
            'custom_fields' => [$link->id => 'not a url'],
        ])->assertSessionHasErrors("custom_fields.{$link->id}");
    }

    public function test_boolean_value_is_serialised_and_cast_back(): void
    {
        $user = User::factory()->create();
        $field = CustomField::factory()->create(['type' => CustomFieldType::Boolean]);
        $item = Item::factory()->create(['type' => ItemType::Item]);

        $this->actingAs($user)->put("/items/{$item->id}", [
            'type' => 'item',
            'name' => $item->name,
            'custom_fields' => [$field->id => true],
        ])->assertRedirect();

        $this->assertSame('1', $item->customFieldValues()->where('custom_field_id', $field->id)->value('value'));

        $this->actingAs($user)->get("/items/{$item->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->where('item.custom_fields.0.value', true));
    }

    public function test_emptying_a_value_removes_it(): void
    {
        $user = User::factory()->create();
        $field = CustomField::factory()->create(['type' => CustomFieldType::Text]);
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $item->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'old']);

        $this->actingAs($user)->put("/items/{$item->id}", [
            'type' => 'item',
            'name' => $item->name,
            'custom_fields' => [$field->id => ''],
        ])->assertRedirect();

        $this->assertSame(0, $item->customFieldValues()->count());
    }

    public function test_system_field_values_survive_an_item_update(): void
    {
        $user = User::factory()->create();
        $system = CustomField::factory()->system('homebox_id')->create();
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $item->customFieldValues()->create(['custom_field_id' => $system->id, 'value' => 'uuid-123']);

        $this->actingAs($user)->put("/items/{$item->id}", [
            'type' => 'item',
            'name' => 'Renamed',
            'custom_fields' => [],
        ])->assertRedirect();

        $this->assertSame('uuid-123', $item->customFieldValues()->where('custom_field_id', $system->id)->value('value'));
    }

    public function test_deleting_item_cascades_values(): void
    {
        $field = CustomField::factory()->create();
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $value = $item->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'x']);

        $item->delete();

        $this->assertModelMissing($value);
    }

    public function test_deleting_definition_cascades_values(): void
    {
        $field = CustomField::factory()->create();
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $value = $item->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => 'x']);

        $field->delete();

        $this->assertModelMissing($value);
    }
}
