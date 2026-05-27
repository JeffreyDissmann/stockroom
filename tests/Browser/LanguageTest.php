<?php

declare(strict_types=1);

use App\Models\User;

it('renders the interface in German for a German-locale user', function () {
    $this->actingAs(User::factory()->create(['locale' => 'de']));

    visit('/dashboard')
        ->assertSee('Übersicht')         // top navigation
        ->assertSee('Willkommen zurück') // dashboard heading
        ->assertDontSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

it('renders the interface in English by default', function () {
    $this->actingAs(User::factory()->create(['locale' => 'en']));

    visit('/dashboard')
        ->assertSee('Dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

it('switches language from settings and persists the choice', function () {
    $user = User::factory()->create(['locale' => 'en']);
    $this->actingAs($user);

    $page = visit('/settings/language');

    // Starts in English.
    $page->assertSee('Language')
        ->assertSee('Settings');

    // Switching to German re-renders the whole app via the redirect-back.
    $page->click('Deutsch')
        ->assertSee('Sprache')
        ->assertSee('Einstellungen')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->locale)->toBe('de');

    // And back to English.
    $page->click('English')
        ->assertSee('Language')
        ->assertSee('Settings')
        ->assertNoJavaScriptErrors();
});
