<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires admin to view the preferences page', function () {
    $this->actingAs(User::factory()->create()) // non-admin
        ->get('/household/preferences')
        ->assertForbidden();
});

it('renders the preferences page for an admin with the current box tag and tag list', function () {
    $boxTag = Tag::query()->where('name', 'Box')->firstOrFail();
    Tag::factory()->create(['name' => 'Tools']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/household/preferences')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('household/Preferences')
            ->where('preferences.box_tag_id', $boxTag->id)
            ->has('tags', 2));
});

it('requires admin to update preferences', function () {
    $tag = Tag::factory()->create();

    $this->actingAs(User::factory()->create()) // non-admin
        ->put('/household/preferences', ['box_tag_id' => $tag->id])
        ->assertForbidden();
});

it('updates the box tag setting', function () {
    $newTag = Tag::factory()->create(['name' => 'Packaging']);

    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['box_tag_id' => $newTag->id])
        ->assertRedirect();

    expect(Setting::get('box_tag_id'))->toBe($newTag->id);
});

it('allows clearing the box tag (admin opted out of auto-tagging)', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['box_tag_id' => null])
        ->assertRedirect();

    expect(Setting::get('box_tag_id'))->toBeNull();
});

it('rejects an invalid tag id', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['box_tag_id' => 99999])
        ->assertSessionHasErrors('box_tag_id');
});
