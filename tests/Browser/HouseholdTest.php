<?php

declare(strict_types=1);

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

it('renders the members page without errors', function () {
    $page = visit('/household/members');

    $page->assertSee('Members')->assertNoJavaScriptErrors();
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
