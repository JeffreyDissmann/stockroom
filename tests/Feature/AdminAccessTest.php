<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every household-management / tag write route is gated behind the `admin`
     * gate. The gate runs before validation, so a non-admin is 403 regardless of payload.
     *
     * @return array<int, array{string, string, array<string, mixed>}>
     */
    private function gatedRoutes(): array
    {
        return [
            ['post', '/tags', ['name' => 'X']],
            ['post', '/household/custom-fields', ['name' => 'X', 'type' => 'text']],
            ['post', '/household/invitations', []],
            ['post', '/household/reset', []],
            ['post', '/household/import', ['url' => 'https://x.test', 'username' => 'a@b.c', 'password' => 'x']],
            ['post', '/household/search-index', []],
            ['get', '/household/backup/export', []],
        ];
    }

    public function test_non_admins_are_forbidden_from_management_routes(): void
    {
        $this->actingAs(User::factory()->create()); // non-admin (default)

        foreach ($this->gatedRoutes() as [$method, $uri, $data]) {
            $response = $method === 'get' ? $this->get($uri) : $this->post($uri, $data);

            $this->assertSame(403, $response->status(), "{$method} {$uri} should be forbidden for non-admins");
        }
    }

    public function test_admins_can_reach_management_routes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/tags', ['name' => 'Tools'])->assertRedirect();
        $this->assertDatabaseHas('tags', ['name' => 'Tools']);

        $this->actingAs($admin)->post('/household/custom-fields', ['name' => 'Color', 'type' => 'text'])->assertRedirect();
        $this->assertDatabaseHas('custom_fields', ['name' => 'Color']);

        $this->actingAs($admin)->post('/household/invitations', ['label' => 'For Anna'])->assertRedirect();
        $this->assertDatabaseHas('invitations', ['label' => 'For Anna']);
    }

    public function test_non_admins_can_still_manage_inventory_and_assign_tags(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        $this->actingAs($user)->post('/items', [
            'name' => 'Drill',
            'type' => ItemType::Item->value,
            'tags' => [$tag->id],
        ])->assertRedirect();

        $item = Item::where('name', 'Drill')->firstOrFail();
        $this->assertTrue($item->tags->contains($tag));
    }

    public function test_non_admins_may_view_the_members_page_read_only(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/household/members')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('household/Members')
                ->where('auth.user.is_admin', false)
            );
    }

    public function test_the_admin_flag_is_shared_to_the_frontend(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('auth.user.is_admin', true));
    }
}
