<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Jordan Diss']));
});

it('loads the profile settings page', function () {
    $page = visit('/settings/profile');

    $page->assertSee('Profile information')
        ->assertValue('#name', 'Jordan Diss')
        ->assertNoJavaScriptErrors();
});

it('loads the password settings page', function () {
    $page = visit('/settings/password');

    $page->assertSee('Update password')
        ->assertPresent('#current_password')
        ->assertPresent('#password')
        ->assertNoJavaScriptErrors();
});

it('shows the bottom tab bar on a mobile viewport', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->assertVisible('.bottom-tabs')
        ->assertSee('More')
        ->assertNoJavaScriptErrors();
});

it('opens the assistant from the mobile more menu', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->click('More')
        ->click('Assistant')
        ->assertSee('Ask me where something is') // the panel's empty state
        ->assertNoJavaScriptErrors();
});
