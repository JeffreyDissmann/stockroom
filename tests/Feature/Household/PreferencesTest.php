<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Jobs\RelinkAllPaperlessDocumentsJob;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

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

it('updates the home assistant tag setting', function () {
    $tag = Tag::factory()->create(['name' => 'Smart Home']);

    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['home_assistant_tag_id' => $tag->id])
        ->assertRedirect();

    expect(Setting::get('home_assistant_tag_id'))->toBe($tag->id);
});

it('rejects an invalid home assistant tag id', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put('/household/preferences', ['home_assistant_tag_id' => 99999])
        ->assertSessionHasErrors('home_assistant_tag_id');
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

it('queues the relink-all job and seeds the cache status to running', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');

    Bus::fake();

    // Three items pointing at two distinct docs — relink should report 2.
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 100]);
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 100]);
    Item::factory()->create()->paperlessLinks()->create(['paperless_document_id' => 200]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/household/preferences/paperless/relink-all')
        ->assertRedirect();

    Bus::assertDispatched(RelinkAllPaperlessDocumentsJob::class);

    // Cache is seeded synchronously so the redirect-followed edit() picks it
    // up and the UI shows the progress bar without waiting on the worker.
    $status = Cache::get(RelinkAllPaperlessDocumentsJob::STATUS_KEY);
    expect($status)
        ->toMatchArray(['state' => 'running', 'done' => 0, 'failed' => 0, 'total' => 2]);
});

it('does not dispatch the relink job when no documents are linked', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');

    Bus::fake();
    Cache::forget(RelinkAllPaperlessDocumentsJob::STATUS_KEY);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/household/preferences/paperless/relink-all')
        ->assertRedirect();

    Bus::assertNothingDispatched();
    expect(Cache::get(RelinkAllPaperlessDocumentsJob::STATUS_KEY))->toBeNull();
});

it('404s the relink endpoint when Paperless is not configured', function () {
    config()->set('paperless.url', '');

    $this->actingAs(User::factory()->admin()->create())
        ->post('/household/preferences/paperless/relink-all')
        ->assertNotFound();
});

it('denies the relink endpoint to non-admins', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');

    $this->actingAs(User::factory()->create())
        ->post('/household/preferences/paperless/relink-all')
        ->assertForbidden();
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
