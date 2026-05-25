<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->get('/activity')->assertRedirect('/login');
    }

    public function test_item_create_and_update_are_logged_with_a_causer(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);
        $item->update(['name' => 'Cordless Drill']);

        $activities = Activity::query()
            ->where('subject_type', Item::class)
            ->where('subject_id', $item->id)
            ->get();

        $this->assertSame(['created', 'updated'], $activities->pluck('event')->sort()->values()->all());
        $this->assertSame($user->id, $activities->last()->causer_id);
    }

    public function test_moving_an_item_logs_the_room_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $garage = Item::factory()->room()->create(['name' => 'Garage']);
        $shed = Item::factory()->room()->create(['name' => 'Shed']);
        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill', 'parent_id' => $garage->id]);

        $this->patch("/items/{$item->id}/move", ['parent_id' => $shed->id])->assertRedirect();

        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('activities.data.0.changes.0.field', 'location')
                ->where('activities.data.0.changes.0.from', 'Garage')
                ->where('activities.data.0.changes.0.to', 'Shed'));
    }

    public function test_activity_page_lists_recent_changes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);
        $item->update(['name' => 'Cordless Drill']);

        $this->get('/activity')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Activity')
                ->has('activities.data', 2)
                ->where('activities.data.0.event', 'updated')
                ->where('activities.data.0.changes.0.field', 'name')
                ->where('activities.data.0.changes.0.from', 'Drill')
                ->where('activities.data.0.changes.0.to', 'Cordless Drill'));
    }
}
