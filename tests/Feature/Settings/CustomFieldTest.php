<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->get('/household/custom-fields')->assertRedirect('/login');
    }

    public function test_admin_can_create_a_custom_field(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/custom-fields', ['name' => 'Color', 'type' => 'text'])
            ->assertRedirect();

        $field = CustomField::firstOrFail();
        $this->assertSame('Color', $field->name);
        $this->assertSame('color', $field->key);
        $this->assertSame(CustomFieldType::Text, $field->type);
        $this->assertFalse($field->is_system);
    }

    public function test_create_defaults_to_not_searchable(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/custom-fields', ['name' => 'Color', 'type' => 'text']);

        $this->assertFalse(CustomField::firstOrFail()->is_searchable);
    }

    public function test_admin_can_create_a_searchable_field(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/custom-fields', ['name' => 'Notes', 'type' => 'text', 'searchable' => true]);

        $this->assertTrue(CustomField::firstOrFail()->is_searchable);
    }

    public function test_admin_can_toggle_searchable_on_update(): void
    {
        $field = CustomField::factory()->searchable()->create(['name' => 'Notes']);
        $this->assertTrue($field->is_searchable);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/household/custom-fields/{$field->id}", ['name' => 'Notes', 'type' => 'text', 'searchable' => false])
            ->assertRedirect();

        $this->assertFalse($field->fresh()->is_searchable);
    }

    public function test_create_assigns_unique_keys(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->post('/household/custom-fields', ['name' => 'Color', 'type' => 'text']);
        $this->actingAs($user)->post('/household/custom-fields', ['name' => 'Color', 'type' => 'number']);

        $this->assertEqualsCanonicalizing(['color', 'color_2'], CustomField::pluck('key')->all());
    }

    public function test_type_must_be_valid(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/custom-fields', ['name' => 'Bad', 'type' => 'rainbow'])
            ->assertSessionHasErrors('type');
    }

    public function test_admin_can_update_a_custom_field(): void
    {
        $field = CustomField::factory()->create(['name' => 'Voltage', 'type' => CustomFieldType::Text]);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/household/custom-fields/{$field->id}", ['name' => 'Voltage (V)', 'type' => 'number'])
            ->assertRedirect();

        $field->refresh();
        $this->assertSame('Voltage (V)', $field->name);
        $this->assertSame(CustomFieldType::Number, $field->type);
    }

    public function test_admin_can_delete_a_custom_field(): void
    {
        $field = CustomField::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/household/custom-fields/{$field->id}")
            ->assertRedirect();

        $this->assertModelMissing($field);
    }

    public function test_system_fields_cannot_be_updated(): void
    {
        $field = CustomField::factory()->system('homebox_id')->create(['name' => 'Homebox ID']);

        $this->actingAs(User::factory()->admin()->create())
            ->put("/household/custom-fields/{$field->id}", ['name' => 'Hacked', 'type' => 'text'])
            ->assertForbidden();

        $this->assertSame('Homebox ID', $field->fresh()->name);
    }

    public function test_system_fields_cannot_be_deleted(): void
    {
        $field = CustomField::factory()->system('homebox_id')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/household/custom-fields/{$field->id}")
            ->assertForbidden();

        $this->assertModelExists($field);
    }
}
