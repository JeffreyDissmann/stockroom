<?php

declare(strict_types=1);

use App\Models\Invitation;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;

// Each Household subpage must render without JavaScript errors. This is the
// canary for the class of bugs where a single template expression — like a
// missing Wayfinder method or a typo'd binding — throws at render time,
// silently bailing Vue out of part of the subtree, leaving the page mostly
// intact but missing the broken section (the download button in our case).
//
// `assertNoJavaScriptErrors()` would have caught that immediately.

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('renders the Backup & Import page with all three sections and a working download link', function () {
    $page = visit('/household/backup');

    $page
        ->assertSee('Backup & import')
        // BackupRestore section — the download button that vanished when
        // backup.exportMethod() was undefined. With Button `as-child`, the
        // data-test attribute lands on the rendered <a>, so it's the
        // element itself (not a child) we assert against.
        ->assertPresent('@backup-download')
        ->assertAttribute('@backup-download', 'href', '/household/backup/export')
        ->assertPresent('@backup-file')
        // HomeBox section
        ->assertSee('Import from Homebox')
        ->assertPresent('#homebox-url')
        ->assertPresent('#homebox-username')
        ->assertPresent('#homebox-password')
        // DangerZone — every include_* checkbox is reachable.
        ->assertPresent('@wipe-include-tags')
        ->assertPresent('@wipe-include-custom-fields')
        ->assertPresent('@wipe-include-activity')
        ->assertPresent('@wipe-button')
        ->assertNoJavaScriptErrors();
});

it('renders the custom fields page without errors', function () {
    $page = visit('/household/custom-fields');

    $page->assertSee('Custom fields')->assertNoJavaScriptErrors();
});

it('renders the search index page without errors', function () {
    $page = visit('/household/search-index');

    $page->assertSee('Search index')->assertNoJavaScriptErrors();
});

// The mail side of these flows runs in the app SERVER process, where the
// test env's array mailer swallows sends — Notification::fake() here in
// the test process would neither apply nor be assertable. The HTTP-level
// send behaviour is covered by InvitationEmailTest.
it('creates and emails an invite from the members form', function () {
    $page = visit('/household/members');

    $page->assertPresent('@invite-email')
        ->fill('@invite-email', 'anna@example.com')
        ->click('Create invite link')
        ->assertPresent('@invite-mail-sent')
        ->assertPresent('@invite-sent-to')
        ->assertSee('anna@example.com')
        ->assertPresent('@invite-resend')
        ->assertNoJavaScriptErrors();

    expect(Invitation::sole()->email)->toBe('anna@example.com');
});

it('re-sends an emailed invite from the list', function () {
    Invitation::factory()->emailed('anna@example.com')->create();

    $page = visit('/household/members');

    $page->click('@invite-resend')
        ->assertPresent('@invite-mail-sent')
        ->assertNoJavaScriptErrors();
});

it('keeps the copy-paste-only invite flow free of email artifacts', function () {
    Invitation::factory()->create(['label' => 'For Anna']);

    $page = visit('/household/members');

    $page->assertSee('For Anna')
        ->assertMissing('@invite-resend')
        ->assertMissing('@invite-sent-to')
        ->assertNoJavaScriptErrors();
});

it('renders the members page without errors', function () {
    $page = visit('/household/members');

    $page->assertSee('Members')->assertNoJavaScriptErrors();
});

it('renders the preferences page with the Box tag picker pre-selected', function () {
    // RefreshDatabase has just run the settings migration, so the Box tag
    // exists and box_tag_id points at it — the dropdown must reflect that
    // out of the box. No render-time template errors either.
    $page = visit('/household/preferences');

    $page->assertSee('Preferences')
        ->assertPresent('@box-tag-select')
        // The Home Assistant tag picker is hidden until a tag has been created
        // (the setting is null on a fresh install).
        ->assertMissing('@home-assistant-tag-select')
        ->assertPresent('@preferences-save')
        ->assertNoJavaScriptErrors();
});

it('shows the Home Assistant tag picker once a tag has been configured', function () {
    $tag = Tag::factory()->create(['name' => 'HomeAssistant']);
    Setting::set('home_assistant_tag_id', $tag->id);

    $page = visit('/household/preferences');

    $page->assertPresent('@home-assistant-tag-select')
        ->assertNoJavaScriptErrors();
});

it('shows the Paperless intake destination picker when Paperless is configured', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');

    $page = visit('/household/preferences');

    $page->assertSee('Preferences')
        ->assertPresent('@paperless-parent-picker')
        ->assertPresent('@paperless-parent-trigger')
        // Both repair actions: full relink + the read-only metadata refresh.
        ->assertPresent('@paperless-relink-all')
        ->assertPresent('@paperless-refresh-metadata')
        ->assertNoJavaScriptErrors();
});

it('hides the Paperless intake destination picker when Paperless is not configured', function () {
    // Default test env has PAPERLESS_URL / PAPERLESS_TOKEN unset, so the
    // features.paperless flag is false and the prefs row should vanish.
    config()->set('paperless.url', '');
    config()->set('paperless.token', '');

    $page = visit('/household/preferences');

    $page->assertSee('Preferences')
        ->assertMissing('@paperless-parent-picker')
        ->assertMissing('@paperless-parent-trigger')
        ->assertNoJavaScriptErrors();
});

it('redirects the legacy import URL to the consolidated page', function () {
    // The visit() helper opens the URL in a real browser and follows the
    // 302 from Route::redirect. We assert content from the destination page
    // rather than the URL, because Inertia's history dance can leave the
    // browser bar momentarily on the source URL.
    $page = visit('/household/import');

    $page->assertSee('Backup & import')
        ->assertPresent('@backup-download')
        ->assertNoJavaScriptErrors();
});
