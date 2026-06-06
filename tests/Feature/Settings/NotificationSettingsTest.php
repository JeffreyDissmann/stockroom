<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('displays the notifications settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('settings/Notifications'));
});

it('toggles the maintenance digest opt-in', function () {
    $user = User::factory()->create(); // defaults to opted in

    $this->actingAs($user)
        ->patch(route('notifications.update'), ['maintenance_digest_opt_in' => false])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($user->refresh()->maintenance_digest_opt_in)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('notifications.update'), ['maintenance_digest_opt_in' => true]);

    expect($user->refresh()->maintenance_digest_opt_in)->toBeTrue();
});

it('requires the flag to be present and boolean', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('notifications.edit'))
        ->patch(route('notifications.update'), [])
        ->assertSessionHasErrors('maintenance_digest_opt_in');

    expect($user->refresh()->maintenance_digest_opt_in)->toBeTrue();
});

it('requires authentication', function () {
    $this->get(route('notifications.edit'))->assertRedirect(route('login'));
});
