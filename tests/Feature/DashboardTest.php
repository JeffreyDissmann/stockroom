<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
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

    public function test_it_reports_suggestions_waiting_on_a_decision()
    {
        $this->actingAs(User::factory()->create());

        $box = Item::factory()->create(['name' => 'Moving box']);
        ItemProposal::factory()->count(2)->for($box)->create();
        ItemProposal::factory()->accepted()->create();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('proposals.pending', 2)
                ->has('proposals.items', 1)
                ->where('proposals.items.0.name', 'Moving box')
                ->where('proposals.items.0.count', 2));
    }

    public function test_it_reports_photos_nobody_has_looked_at()
    {
        $this->actingAs(User::factory()->create());

        ItemImage::factory()->for(Item::factory()->create())->create(['analyzed_at' => null]);
        ItemImage::factory()->for(Item::factory()->create())->create(['analyzed_at' => now()]);

        $this->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('proposals.unreviewed', 1));
    }

    public function test_it_does_not_nag_about_unreviewed_photos_while_ai_is_off()
    {
        $this->actingAs(User::factory()->create());
        config(['ai.enabled' => false]);

        ItemImage::factory()->for(Item::factory()->create())->create(['analyzed_at' => null]);

        // Nothing is going to review them, so counting them would only be a
        // reproach the user cannot act on.
        $this->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('proposals.unreviewed', 0));
    }
}
