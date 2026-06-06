<?php

declare(strict_types=1);

use App\Enums\MaintenanceIntervalUnit;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the maintenance section with tasks, history and all action triggers', function () {
    // Canary for the whole section: one task of each visual state (overdue
    // interval, custom-rule calendar) plus a history entry. A broken
    // template expression would silently blank the section — only
    // assertNoJavaScriptErrors catches that.
    $item = Item::factory()->create(['name' => 'Smoke detector']);
    MaintenanceTask::factory()->for($item)->interval(6, MaintenanceIntervalUnit::Months)->overdue(3)->create([
        'title' => 'Change batteries',
        'last_completed_at' => today()->subMonths(6),
    ]);
    MaintenanceTask::factory()->for($item)->calendar('FREQ=WEEKLY;BYDAY=MO,WE,FR')->dueSoon(10)->create([
        'title' => 'Custom-rule task',
    ]);
    MaintenanceEntry::factory()->for($item)->create(['notes' => 'Replaced the bracket.']);

    $page = visit("/items/{$item->id}");

    $page->assertSee('Change batteries')
        ->assertSee('Custom-rule task')
        ->assertSee('Replaced the bracket.')
        ->assertPresent('@maintenance-section')
        ->assertPresent('@maintenance-task-add')
        ->assertPresent('@maintenance-entry-add')
        ->assertPresent('@maintenance-mark-done')
        ->assertPresent('@maintenance-due-badge')
        ->assertPresent('@maintenance-task-menu')
        ->assertPresent('@maintenance-entry-delete')
        ->assertNoJavaScriptErrors();
});

it('renders the empty state without errors', function () {
    $item = Item::factory()->create();

    $page = visit("/items/{$item->id}");

    $page->assertPresent('@maintenance-section')
        ->assertPresent('@maintenance-task-add')
        ->assertNoJavaScriptErrors();
});

it('creates an interval task through the dialog', function () {
    $item = Item::factory()->create();

    $page = visit("/items/{$item->id}");

    // Dialog defaults: interval, every 6 months — only the title is needed.
    $page->click('@maintenance-task-add')
        ->fill('#task-title', 'Descale machine')
        ->click('@maintenance-task-submit')
        ->assertSee('Descale machine')
        ->assertNoJavaScriptErrors();

    $task = MaintenanceTask::sole();
    expect($task->title)->toBe('Descale machine')
        ->and($task->next_due_at->toDateString())->toBe(today()->addMonthsNoOverflow(6)->toDateString());
});

it('creates a one-off task with a due date', function () {
    $item = Item::factory()->create();
    $dueAt = today()->addDays(14)->toDateString();

    $page = visit("/items/{$item->id}");

    $page->click('@maintenance-task-add')
        ->fill('#task-title', 'Replace water filter')
        ->select('#task-schedule-type', 'one_off')
        ->fill('#task-due-date', $dueAt)
        ->click('@maintenance-task-submit')
        ->assertSee('Replace water filter')
        ->assertNoJavaScriptErrors();

    expect(MaintenanceTask::sole()->next_due_at->toDateString())->toBe($dueAt);
});

it('creates a calendar task from the yearly preset', function () {
    $item = Item::factory()->create();

    $page = visit("/items/{$item->id}");

    $page->click('@maintenance-task-add')
        ->fill('#task-title', 'Spring service')
        ->select('#task-schedule-type', 'calendar')
        ->select('#task-preset', 'yearly_on')
        ->select('#task-preset-month', '4')
        ->fill('#task-preset-day', '1')
        ->click('@maintenance-task-submit')
        ->assertSee('Spring service')
        ->assertNoJavaScriptErrors();

    expect(MaintenanceTask::sole()->rrule)->toBe('FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1');
});

it('marks a task done through the prefilled dialog', function () {
    $item = Item::factory()->create();
    $task = MaintenanceTask::factory()->for($item)->interval(1, MaintenanceIntervalUnit::Months)->overdue(5)->create();

    $page = visit("/items/{$item->id}");

    $page->click('@maintenance-mark-done')
        ->assertValue('#done-date', today()->toDateString())
        ->fill('#done-notes', 'Done via browser test.')
        ->click('@maintenance-done-submit')
        ->assertSee('Done via browser test.')
        ->assertNoJavaScriptErrors();

    expect(MaintenanceEntry::sole()->notes)->toBe('Done via browser test.')
        ->and($task->fresh()->next_due_at->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('skips a calendar occurrence from the task menu', function () {
    $item = Item::factory()->create();
    $task = MaintenanceTask::factory()->for($item)->calendar('FREQ=MONTHLY;BYDAY=-1FR')->create([
        'next_due_at' => today()->addDays(3),
    ]);

    $page = visit("/items/{$item->id}");

    $page->click('@maintenance-task-menu')
        ->click('@maintenance-task-skip')
        ->assertNoJavaScriptErrors();

    expect($task->fresh()->next_due_at->toDateString())
        ->not->toBe(today()->addDays(3)->toDateString())
        ->and(MaintenanceEntry::count())->toBe(0);
});

it('edits a task through the dialog with hydrated fields', function () {
    $item = Item::factory()->create();
    MaintenanceTask::factory()->for($item)->interval(6, MaintenanceIntervalUnit::Months)->create(['title' => 'Old title']);

    $page = visit("/items/{$item->id}");

    $page->click('@maintenance-task-menu')
        ->click('@maintenance-task-edit')
        ->assertValue('#task-title', 'Old title')
        ->assertValue('#task-interval-value', '6')
        ->fill('#task-title', 'New title')
        ->click('@maintenance-task-submit')
        ->assertSee('New title')
        ->assertNoJavaScriptErrors();

    expect(MaintenanceTask::sole()->title)->toBe('New title');
});

it('renders the global maintenance page with filters, rows and nav entry', function () {
    $item = Item::factory()->create(['name' => 'Boiler']);
    MaintenanceTask::factory()->for($item)->overdue(4)->create(['title' => 'Annual service']);
    MaintenanceTask::factory()->for($item)->dueSoon(30)->create(['title' => 'Pressure check']);

    $page = visit('/maintenance');

    $page->assertSee('Annual service')
        ->assertSee('Pressure check')
        ->assertSee('Boiler')
        // The nav label resolves in BOTH locales' files — a key missing
        // from en surfaces as the raw "nav.maintenance" string.
        ->assertDontSee('nav.maintenance')
        ->assertPresent('@maintenance-filter-all')
        ->assertPresent('@maintenance-filter-overdue')
        ->assertPresent('@maintenance-filter-due-soon')
        ->assertPresent('@maintenance-global-row')
        ->assertPresent('@maintenance-global-done')
        ->assertNoJavaScriptErrors();

    // The overdue filter narrows the list down.
    $page->click('@maintenance-filter-overdue')
        ->assertSee('Annual service')
        ->assertDontSee('Pressure check')
        ->assertNoJavaScriptErrors();
});

it('marks a task done from the global page', function () {
    $item = Item::factory()->create();
    $task = MaintenanceTask::factory()->for($item)->interval(1, MaintenanceIntervalUnit::Months)->overdue(2)->create();

    $page = visit('/maintenance');

    $page->click('@maintenance-global-done')
        ->assertValue('#done-date', today()->toDateString())
        ->click('@maintenance-done-submit')
        ->assertNoJavaScriptErrors();

    expect(MaintenanceEntry::sole()->maintenance_task_id)->toBe($task->id)
        ->and($task->fresh()->next_due_at->toDateString())->toBe(today()->addMonthNoOverflow()->toDateString());
});

it('shows the due-soon card on the dashboard and links through', function () {
    $item = Item::factory()->create(['name' => 'Boiler']);
    MaintenanceTask::factory()->for($item)->overdue(4)->create(['title' => 'Annual service']);

    $page = visit('/dashboard');

    $page->assertPresent('@dashboard-maintenance-card')
        ->assertPresent('@dashboard-maintenance-row')
        ->assertSee('Annual service')
        ->assertSee('Boiler')
        ->assertNoJavaScriptErrors();
});

it('renders the empty global page without errors', function () {
    $page = visit('/maintenance');

    $page->assertPresent('@maintenance-filter-all')->assertNoJavaScriptErrors();
});

it('toggles the digest opt-in on the notification settings page', function () {
    $page = visit('/settings/notifications');

    // Default is opted-in; one click opts out and Save persists it. The
    // "Saved." confirmation gates the DB assertion — clicking Save only
    // STARTS the async PATCH.
    $page->assertPresent('@maintenance-digest-toggle')
        ->click('@maintenance-digest-toggle')
        ->click('Save')
        ->assertSee('Saved.')
        ->assertNoJavaScriptErrors();

    expect(User::sole()->maintenance_digest_opt_in)->toBeFalse();
});

it('logs an ad-hoc entry through the dialog', function () {
    $item = Item::factory()->create();

    $page = visit("/items/{$item->id}");

    $page->click('@maintenance-entry-add')
        ->fill('#entry-notes', 'Repaired the drawer handle.')
        ->click('@maintenance-entry-submit')
        ->assertSee('Repaired the drawer handle.')
        ->assertNoJavaScriptErrors();

    $entry = MaintenanceEntry::sole();
    expect($entry->maintenance_task_id)->toBeNull()
        ->and($entry->notes)->toBe('Repaired the drawer handle.');
});
