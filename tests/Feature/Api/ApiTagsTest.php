<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_require_authentication(): void
    {
        $this->getJson('/api/v1/tags')->assertUnauthorized();
    }

    public function test_lists_tags_alphabetically(): void
    {
        // A default "Box" tag is seeded by the settings migration, so it is
        // always present alongside whatever the test creates.
        Tag::factory()->create(['name' => 'Zebra']);
        Tag::factory()->create(['name' => 'Apple']);

        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/tags')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Apple')
            ->assertJsonPath('data.1.name', 'Box')
            ->assertJsonPath('data.2.name', 'Zebra')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'color']]]);
    }
}
