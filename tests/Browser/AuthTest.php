<?php

declare(strict_types=1);

use App\Models\User;

it('logs in with valid credentials and lands on the dashboard', function () {
    User::factory()->create([
        'email' => 'admin@stockroom.local',
        // UserFactory default password is "password".
    ]);

    $page = visit('/login');

    $page->type('#email', 'admin@stockroom.local')
        ->type('#password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

it('rejects invalid credentials', function () {
    User::factory()->create(['email' => 'admin@stockroom.local']);

    $page = visit('/login');

    $page->type('#email', 'admin@stockroom.local')
        ->type('#password', 'wrong-password')
        ->click('Log in')
        ->assertPathIs('/login')
        ->assertSee('These credentials do not match our records.');
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
