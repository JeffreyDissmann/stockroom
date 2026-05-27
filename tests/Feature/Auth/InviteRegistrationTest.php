<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_invite_link_shows_the_registration_page(): void
    {
        $inviter = User::factory()->create(['name' => 'Jeff']);
        $invitation = Invitation::factory()->create(['created_by' => $inviter->id]);

        $response = $this->get("/register/{$invitation->token}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('auth/Register')
            ->where('token', $invitation->token)
            ->where('invitedBy', 'Jeff')
        );
    }

    public function test_an_unknown_token_shows_the_invalid_page(): void
    {
        $response = $this->get('/register/this-token-does-not-exist');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('auth/InviteInvalid'));
    }

    public function test_an_expired_token_shows_the_invalid_page(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $this->get("/register/{$invitation->token}")
            ->assertInertia(fn ($page) => $page->component('auth/InviteInvalid'));
    }

    public function test_an_already_accepted_token_shows_the_invalid_page(): void
    {
        $invitation = Invitation::factory()->accepted()->create();

        $this->get("/register/{$invitation->token}")
            ->assertInertia(fn ($page) => $page->component('auth/InviteInvalid'));
    }

    public function test_an_authenticated_user_cannot_visit_the_register_page(): void
    {
        $invitation = Invitation::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/register/{$invitation->token}")
            ->assertRedirect('/dashboard');
    }

    public function test_registering_with_a_valid_invite_creates_and_logs_in_the_user(): void
    {
        $invitation = Invitation::factory()->create();

        $response = $this->post("/register/{$invitation->token}", [
            'name' => 'Anna Example',
            'email' => 'anna@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();

        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertSame('Anna Example', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($this->app['auth']->user()->is($user));
    }

    public function test_accepting_an_invite_marks_it_used_with_the_new_user(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post("/register/{$invitation->token}", [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $invitation->refresh();

        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($user->id, $invitation->accepted_by);
        $this->assertFalse($invitation->isPending());
    }

    public function test_an_invite_link_is_single_use(): void
    {
        $invitation = Invitation::factory()->create();
        $payload = fn (string $email) => [
            'name' => 'Person',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];

        $this->post("/register/{$invitation->token}", $payload('first@example.com'))
            ->assertRedirect(route('dashboard', absolute: false));

        $this->post('/logout');

        // Re-using the same token must fail and create no second account.
        $this->post("/register/{$invitation->token}", $payload('second@example.com'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('users', ['email' => 'second@example.com']);
    }

    public function test_registering_with_an_expired_token_is_rejected(): void
    {
        $invitation = Invitation::factory()->expired()->create();

        $this->post("/register/{$invitation->token}", [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    public function test_registration_requires_name_email_and_password(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post("/register/{$invitation->token}", [])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $invitation = Invitation::factory()->create();

        $this->post("/register/{$invitation->token}", [
            'name' => 'Anna',
            'email' => 'taken@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        // The invite must remain usable after a failed attempt.
        $this->assertTrue($invitation->fresh()->isPending());
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $invitation = Invitation::factory()->create();

        $this->post("/register/{$invitation->token}", [
            'name' => 'Anna',
            'email' => 'anna@example.com',
            'password' => 'password',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
    }
}
