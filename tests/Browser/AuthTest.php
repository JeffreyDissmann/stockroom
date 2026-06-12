<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\User;

// Guest context on purpose — /register/{token} bounces authenticated
// users to the dashboard.
it('prefills the invited email on the registration page', function () {
    $invitation = Invitation::factory()->emailed('anna@example.com')->create();

    $page = visit("/register/{$invitation->token}");

    $page->assertValue('#email', 'anna@example.com')->assertNoJavaScriptErrors();
});

it('logs in with valid credentials and lands on the dashboard', function () {
    User::factory()->create([
        'email' => 'admin@stockroom.local',
        // UserFactory default password is "password".
    ]);

    $page = visit('/login');

    $page->type('#email', 'admin@stockroom.local')
        ->type('#password', 'password')
        ->click('@login-submit')
        ->assertPathIs('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'admin@stockroom.local']);

    $page = visit('/login');

    $page->type('#email', 'admin@stockroom.local')
        ->type('#password', 'wrong-password')
        ->click('@login-submit')
        ->assertPathIs('/login')
        // Assert the validation error is shown without asserting its text —
        // guest pages render in the server's APP_LOCALE, which isn't English
        // in every environment. The visible error is the language-neutral signal.
        ->assertVisible('@login-error');
});

it('shows the running version in the user menu when logged in', function () {
    config(['stockroom.version.tag' => '2026.06.07', 'stockroom.version.commit' => 'abc1234def']);
    $this->actingAs(User::factory()->create());

    $page = visit('/dashboard');
    $page->click('@user-menu')
        ->assertPresent('@user-menu-version')
        ->assertSee('2026.06.07')
        ->assertSee('abc1234')
        ->assertNoJavaScriptErrors();
});

it('logs out via the user menu back to the login screen', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = visit('/dashboard');
    $page->assertSee('Welcome back')
        ->click('@user-menu')
        ->click('Log out')
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});
