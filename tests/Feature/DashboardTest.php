<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_surfaces_value_breakdown_and_activity(): void
    {
        $this->actingAs(User::factory()->create());

        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        Item::factory()->count(2)->create(['parent_id' => $garage->id, 'purchase_price' => 100]);
        // Sold items are excluded from the estimated value.
        Item::factory()->create(['purchase_price' => 500, 'sold_date' => now()]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('stats.rooms', 1)
                ->where('stats.value', 200)
                ->has('rooms', 1)
                ->where('rooms.0.name', 'Garage')
                ->where('rooms.0.count', 2)
                ->has('activity')
                ->has('recent')
                ->has('tags'));
    }
}
