<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Models\Item;
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

it('hydrates the picker with the currently selected parent', function () {
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    Setting::set('paperless_parent_id', $garage->id);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/household/preferences')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('preferences.paperless_parent_id', $garage->id)
            ->where('selectedParent.id', $garage->id)
            ->where('selectedParent.type', 'room'));
});

it('returns rooms and containers from the Paperless parent search endpoint', function () {
    Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->create(['type' => ItemType::Container, 'name' => 'Toolbox']);
    Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']); // excluded

    $this->actingAs(User::factory()->admin()->create())
        ->getJson('/household/preferences/paperless-parent-targets')
        ->assertOk()
        ->assertJsonCount(2, 'targets');
});

it('denies the Paperless parent search endpoint to non-admins', function () {
    $this->actingAs(User::factory()->create())
        ->getJson('/household/preferences/paperless-parent-targets')
        ->assertForbidden();
});

it('exposes features.paperless as true when both URL and token are configured', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/household/preferences')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.paperless', true));
});

it('exposes features.paperless as false when configuration is missing', function () {
    // Half-configured (url only, no token) is still considered disabled —
    // hitting Paperless without a token would just 401 every call.
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', '');

    $this->actingAs(User::factory()->admin()->create())
        ->get('/household/preferences')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('features.paperless', false));
});

it('updates the Paperless parent setting to a container', function () {
    $box = Item::factory()->create(['type' => ItemType::Container, 'name' => 'Inbox']);

    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['paperless_parent_id' => $box->id])
        ->assertRedirect();

    expect(Setting::get('paperless_parent_id'))->toBe($box->id);
});

it('rejects a Paperless parent that is not a room or container', function () {
    $item = Item::factory()->create(['type' => ItemType::Item, 'name' => 'Drill']);

    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['paperless_parent_id' => $item->id])
        ->assertSessionHasErrors('paperless_parent_id');
});

it('allows clearing the Paperless parent (admin opted out)', function () {
    Setting::set('paperless_parent_id', 42);

    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['paperless_parent_id' => null])
        ->assertRedirect();

    expect(Setting::get('paperless_parent_id'))->toBeNull();
});
