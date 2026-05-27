<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ItemType;
use App\Models\Invitation;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class UserActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_promoting_a_member_is_logged_and_shown_in_the_feed(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->patch("/household/members/{$member->id}", ['is_admin' => true])->assertRedirect();

        $activity = Activity::where('log_name', 'user')
            ->where('subject_id', $member->id)
            ->where('event', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);

        // The newest feed entry is the role change, rendered against the `user` subject.
        $this->actingAs($admin)->get('/activity')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('activities.data.0.event', 'updated')
            ->where('activities.data.0.subject_type', 'user')
            ->where('activities.data.0.subject_label', $member->name)
            ->where('activities.data.0.changes.0.field', 'admin'));
    }

    public function test_removing_a_member_is_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->delete("/household/members/{$member->id}")->assertRedirect();

        $this->assertTrue(
            Activity::where('log_name', 'user')->where('subject_id', $member->id)->where('event', 'deleted')->exists(),
        );
    }

    public function test_accepting_an_invite_logs_the_new_user(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post("/register/{$invitation->token}", [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $user = User::where('email', 'anna@example.com')->firstOrFail();

        $this->assertTrue(
            Activity::where('log_name', 'user')->where('subject_id', $user->id)->where('event', 'created')->exists(),
        );
    }

    public function test_editing_an_item_does_not_create_a_user_activity(): void
    {
        $this->actingAs(User::factory()->create());
        $item = Item::factory()->create(['type' => ItemType::Item]);
        $item->update(['name' => 'Renamed']);

        // Only the user's own creation should be a `user` log; item edits stay `item`.
        $this->assertSame(1, Activity::where('log_name', 'user')->count());
    }
}
