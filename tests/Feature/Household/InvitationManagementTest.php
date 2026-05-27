<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_members_page(): void
    {
        $this->get('/household/members')->assertRedirect('/login');
    }

    public function test_the_members_page_lists_invites_and_people(): void
    {
        $user = User::factory()->create(['name' => 'Owner']);
        Invitation::factory()->create(['created_by' => $user->id, 'label' => 'For Anna']);

        $response = $this->actingAs($user)->get('/household/members');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('household/Members')
            ->has('invitations', 1)
            ->where('invitations.0.label', 'For Anna')
            ->where('invitations.0.created_by', 'Owner')
            ->has('invitations.0.url')
            ->has('members', 1)
            ->where('members.0.is_self', true)
            ->where('members.0.email', $user->email)
        );
    }

    public function test_the_members_page_only_lists_pending_invites(): void
    {
        $user = User::factory()->create();
        Invitation::factory()->create(['created_by' => $user->id]);          // pending
        Invitation::factory()->expired()->create(['created_by' => $user->id]);
        Invitation::factory()->accepted()->create(['created_by' => $user->id]);

        $this->actingAs($user)->get('/household/members')
            ->assertInertia(fn ($page) => $page->has('invitations', 1));
    }

    public function test_an_invite_can_be_created(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/household/invitations', ['label' => 'For Anna'])
            ->assertRedirect();

        $invitation = Invitation::firstOrFail();
        $this->assertSame('For Anna', $invitation->label);
        $this->assertSame($user->id, $invitation->created_by);
        $this->assertNotEmpty($invitation->token);
        $this->assertTrue($invitation->isPending());
        $this->assertEqualsWithDelta(
            now()->addDays(Invitation::LIFETIME_DAYS)->timestamp,
            $invitation->expires_at->timestamp,
            5,
        );
    }

    public function test_an_invite_can_be_created_without_a_label(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/household/invitations', [])->assertRedirect();

        $this->assertNull(Invitation::firstOrFail()->label);
    }

    public function test_the_label_is_length_limited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/household/invitations', ['label' => str_repeat('x', 101)])
            ->assertSessionHasErrors('label');

        $this->assertSame(0, Invitation::count());
    }

    public function test_guests_cannot_create_invites(): void
    {
        $this->post('/household/invitations', ['label' => 'sneaky'])->assertRedirect('/login');

        $this->assertSame(0, Invitation::count());
    }

    public function test_a_pending_invite_can_be_revoked(): void
    {
        $user = User::factory()->create();
        $invitation = Invitation::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)->delete("/household/invitations/{$invitation->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
    }

    public function test_an_accepted_invite_cannot_be_revoked(): void
    {
        $user = User::factory()->create();
        $invitation = Invitation::factory()->accepted()->create(['created_by' => $user->id]);

        $this->actingAs($user)->delete("/household/invitations/{$invitation->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('invitations', ['id' => $invitation->id]);
    }

    public function test_an_expired_invite_cannot_be_revoked(): void
    {
        $user = User::factory()->create();
        $invitation = Invitation::factory()->expired()->create(['created_by' => $user->id]);

        $this->actingAs($user)->delete("/household/invitations/{$invitation->id}")
            ->assertForbidden();
    }

    public function test_guests_cannot_revoke_invites(): void
    {
        $invitation = Invitation::factory()->create();

        $this->delete("/household/invitations/{$invitation->id}")->assertRedirect('/login');

        $this->assertDatabaseHas('invitations', ['id' => $invitation->id]);
    }
}
