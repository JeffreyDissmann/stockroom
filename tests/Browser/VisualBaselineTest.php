<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;

/**
 * TEMPORARY — visual baseline for the Tailwind 3 -> 4 migration.
 *
 * The suites assert behaviour, not appearance, so they stay green through a
 * visual regression. This captures the main screens before and after so the
 * two can be compared by eye. Delete once the migration has been signed off.
 *
 * Screenshots land in tests/Browser/Screenshots (gitignored).
 *
 * Run it as:
 *   VISUAL_BASELINE=1 SHOT_PREFIX=before ./vendor/bin/pest tests/Browser/VisualBaselineTest.php
 *
 * GOTCHA: the browser plugin empties that directory at the start of every run,
 * so the second pass deletes the first pass's images. Copy them somewhere else
 * before capturing the "after" set, or you end up comparing a set against
 * nothing — which is exactly what happened the first time.
 */
it('captures the guest screen', function () {
    // Captured before authenticating: /login redirects a signed-in session to
    // the dashboard, so shooting it after actingAs() silently produces a second
    // copy of the dashboard rather than the login page.
    visit('/login')
        ->assertSee('Stockroom')
        ->screenshot(true, prefix().'-login');
})->skip(! capturing(), 'Set VISUAL_BASELINE=1 to capture screenshots.');

it('captures the authenticated screens', function () {
    $this->actingAs(User::factory()->admin()->create());

    $room = Item::factory()->create(['name' => 'Garage', 'type' => 'room']);
    $item = Item::factory()->create(['name' => 'Cordless Drill', 'parent_id' => $room->id, 'type' => 'item']);
    $item->tags()->attach(Tag::factory()->create(['name' => 'Tools', 'color' => '#2563eb']));

    $pages = [
        'dashboard' => ['/dashboard', 'Welcome back'],
        'items' => ['/items', 'Garage'],
        // Assert on content unique to the item page: it renders a battery
        // panel and activity feed after hydration, and screenshotting too
        // early yields a blank white page that looks like a broken build.
        'item-show' => ["/items/{$room->id}", 'Cordless Drill'],
        'preferences' => ['/household/preferences', 'Preferences'],
        'members' => ['/household/members', 'Members'],
        'maintenance' => ['/maintenance', 'Maintenance'],
        'tags' => ['/tags', 'Tools'],
    ];

    foreach ($pages as $name => [$url, $marker]) {
        visit($url)
            ->assertSee($marker)
            ->assertNoJavaScriptErrors()
            ->screenshot(true, prefix()."-{$name}");
    }
})->skip(! capturing(), 'Set VISUAL_BASELINE=1 to capture screenshots.');
