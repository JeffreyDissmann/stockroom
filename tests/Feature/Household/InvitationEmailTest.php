<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('creates and emails an invite when an address is given', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create(['name' => 'Jeff']);

    $this->actingAs($admin)
        ->post('/household/invitations', ['email' => 'anna@example.com'])
        ->assertRedirect()
        ->assertSessionHas('invitation_mail', 'sent');

    $invitation = Invitation::sole();
    expect($invitation->email)->toBe('anna@example.com');

    Notification::assertSentOnDemand(
        InvitationInvite::class,
        function (InvitationInvite $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation): bool {
            return $notifiable->routes['mail'] === 'anna@example.com'
                && $notification->invitation->is($invitation);
        },
    );
});

it('creates a copy-paste-only invite when no address is given', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/household/invitations', ['label' => 'For Anna'])
        ->assertRedirect()
        ->assertSessionMissing('invitation_mail');

    expect(Invitation::sole()->email)->toBeNull();
    Notification::assertNothingSent();
});

it('rejects an invalid email address', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/household/invitations', ['email' => 'not-an-email'])
        ->assertSessionHasErrors('email');

    expect(Invitation::count())->toBe(0);
});

it('renders the invite mail with inviter, link and expiry', function () {
    app()->setLocale('en');
    $admin = User::factory()->admin()->create(['name' => 'Jeff']);
    $invitation = Invitation::factory()->emailed('anna@example.com')->create(['created_by' => $admin->id]);

    $mail = (new InvitationInvite($invitation->load('creator')))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toBe('Jeff invited you to Stockroom')
        ->and($mail->actionUrl)->toBe(route('register', $invitation->token))
        ->and(implode("\n", $mail->introLines))->toContain('Jeff invited you')
        ->and(implode("\n", $mail->outroLines))->toContain('expires in 7 days');
});

it('keeps the invite and flashes a failure when the mail transport throws', function () {
    $admin = User::factory()->admin()->create();

    // Force a transport failure: route the default mailer at an
    // unreachable SMTP host for this request.
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.host', '127.0.0.1');
    config()->set('mail.mailers.smtp.port', 1);
    config()->set('mail.mailers.smtp.timeout', 1);

    $this->actingAs($admin)
        ->post('/household/invitations', ['email' => 'anna@example.com'])
        ->assertRedirect()
        ->assertSessionHas('invitation_mail', 'failed');

    // The invite survives the failed send — the link stays copyable.
    expect(Invitation::sole()->email)->toBe('anna@example.com');
});

describe('resend', function () {
    it('re-mails a pending emailed invite', function () {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->emailed('anna@example.com')->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->post("/household/invitations/{$invitation->id}/resend")
            ->assertRedirect()
            ->assertSessionHas('invitation_mail', 'sent');

        Notification::assertSentOnDemand(
            InvitationInvite::class,
            fn (InvitationInvite $n, array $c, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'anna@example.com',
        );
    });

    it('403s for invites without an address — the UI never offers that', function () {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $invitation = Invitation::factory()->create(); // copy-paste invite

        $this->actingAs($admin)
            ->post("/household/invitations/{$invitation->id}/resend")
            ->assertForbidden();

        Notification::assertNothingSent();
    });

    it('rejects no-longer-pending invites with a validation error (stale page)', function (Invitation $invitation) {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post("/household/invitations/{$invitation->id}/resend")
            ->assertRedirect()
            ->assertSessionHasErrors('invitation');

        Notification::assertNothingSent();
    })->with([
        'expired emailed invite' => fn () => Invitation::factory()->emailed()->expired()->create(),
        'accepted emailed invite' => fn () => Invitation::factory()->emailed()->accepted()->create(),
    ]);

    it('is admin-gated', function () {
        Notification::fake();
        $member = User::factory()->create();
        $invitation = Invitation::factory()->emailed()->create();

        $this->actingAs($member)
            ->post("/household/invitations/{$invitation->id}/resend")
            ->assertForbidden();

        Notification::assertNothingSent();
    });
});

it('shares the invite email on the members page payload', function () {
    $admin = User::factory()->admin()->create();
    Invitation::factory()->emailed('anna@example.com')->create(['created_by' => $admin->id]);

    $this->actingAs($admin)->get('/household/members')
        ->assertInertia(fn ($page) => $page->where('invitations.0.email', 'anna@example.com'));
});
