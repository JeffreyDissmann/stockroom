<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_promote_a_member(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->patch("/household/members/{$member->id}", ['is_admin' => true])
            ->assertRedirect();

        $this->assertTrue($member->fresh()->is_admin);
    }

    public function test_an_admin_can_demote_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/household/members/{$other->id}", ['is_admin' => false])
            ->assertRedirect();

        $this->assertFalse($other->fresh()->is_admin);
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->patch("/household/members/{$admin->id}", ['is_admin' => false])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_admin);
    }

    public function test_an_admin_can_remove_another_member(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        Invitation::factory()->create(['created_by' => $member->id]);

        $this->actingAs($admin)->delete("/household/members/{$member->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
        // Their pending invites go with them (FK cascade).
        $this->assertSame(0, Invitation::count());
    }

    public function test_an_admin_cannot_remove_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/household/members/{$admin->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admins_cannot_manage_members(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)->patch("/household/members/{$other->id}", ['is_admin' => true])->assertForbidden();
        $this->actingAs($user)->delete("/household/members/{$other->id}")->assertForbidden();

        $this->assertFalse($other->fresh()->is_admin);
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }
}
