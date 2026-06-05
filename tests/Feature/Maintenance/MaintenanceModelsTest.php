<?php

declare(strict_types=1);

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('orders an item\'s tasks by due date and entries newest first', function () {
    $item = Item::factory()->create();
    $later = MaintenanceTask::factory()->for($item)->create(['next_due_at' => today()->addDays(30)]);
    $sooner = MaintenanceTask::factory()->for($item)->create(['next_due_at' => today()->addDays(5)]);
    $old = MaintenanceEntry::factory()->for($item)->create(['completed_at' => today()->subYear()]);
    $recent = MaintenanceEntry::factory()->for($item)->create(['completed_at' => today()->subDay()]);

    expect($item->maintenanceTasks()->pluck('id')->all())->toEqual([$sooner->id, $later->id])
        ->and($item->maintenanceEntries()->pluck('id')->all())->toEqual([$recent->id, $old->id]);
});

it('deletes tasks and entries with the item', function () {
    $item = Item::factory()->create();
    $task = MaintenanceTask::factory()->for($item)->create();
    MaintenanceEntry::factory()->forTask($task)->create();

    $item->delete();

    expect(MaintenanceTask::count())->toBe(0)
        ->and(MaintenanceEntry::count())->toBe(0);
});

it('keeps entries as ad-hoc history when their task is deleted', function () {
    $task = MaintenanceTask::factory()->create();
    $entry = MaintenanceEntry::factory()->forTask($task)->create();

    $task->delete();

    expect($entry->fresh())
        ->maintenance_task_id->toBeNull()
        ->item_id->toBe($task->item_id);
});

it('keeps entries when the performing user is deleted', function () {
    $user = User::factory()->create();
    $entry = MaintenanceEntry::factory()->create(['performed_by' => $user->id]);

    $user->delete();

    expect($entry->fresh()->performed_by)->toBeNull();
});

it('resolves the latest entry per task', function () {
    $task = MaintenanceTask::factory()->create();
    MaintenanceEntry::factory()->forTask($task)->create(['completed_at' => today()->subMonths(2)]);
    $latest = MaintenanceEntry::factory()->forTask($task)->create(['completed_at' => today()->subDay()]);

    expect($task->latestEntry()->first()->id)->toBe($latest->id);
});

it('scopes overdue and due-within correctly', function () {
    $overdue = MaintenanceTask::factory()->overdue()->create();
    $dueToday = MaintenanceTask::factory()->create(['next_due_at' => today()]);
    $dueSoon = MaintenanceTask::factory()->dueSoon(3)->create();
    MaintenanceTask::factory()->create(['next_due_at' => today()->addDays(30)]);
    MaintenanceTask::factory()->inactive()->create();

    expect(MaintenanceTask::query()->active()->overdue()->pluck('id')->all())->toEqual([$overdue->id])
        ->and(MaintenanceTask::query()->active()->dueWithin(7)->pluck('id')->sort()->values()->all())
        ->toEqual(collect([$dueToday->id, $dueSoon->id])->sort()->values()->all());
});

it('excludes inactive tasks from the active scope', function () {
    MaintenanceTask::factory()->overdue()->inactive()->create();

    expect(MaintenanceTask::query()->active()->count())->toBe(0);
});

it('reports overdue and days-until-due', function () {
    $overdue = MaintenanceTask::factory()->overdue(10)->create();
    $upcoming = MaintenanceTask::factory()->dueSoon(3)->create();
    $archived = MaintenanceTask::factory()->oneOff()->inactive()->create();

    expect($overdue->isOverdue())->toBeTrue()
        ->and($overdue->dueInDays())->toBe(-10)
        ->and($upcoming->isOverdue())->toBeFalse()
        ->and($upcoming->dueInDays())->toBe(3)
        ->and($archived->isOverdue())->toBeFalse()
        ->and($archived->dueInDays())->toBeNull();
});

it('enters the reminder window lead-days before the due date', function () {
    $insideWindow = MaintenanceTask::factory()->dueSoon(3)->create(['reminder_lead_days' => 7]);
    $outsideWindow = MaintenanceTask::factory()->dueSoon(10)->create(['reminder_lead_days' => 7]);
    $overdue = MaintenanceTask::factory()->overdue()->create(['reminder_lead_days' => 7]);

    expect($insideWindow->isWithinReminderWindow())->toBeTrue()
        ->and($outsideWindow->isWithinReminderWindow())->toBeFalse()
        ->and($overdue->isWithinReminderWindow())->toBeTrue();
});

it('casts schedule fields to their enums', function () {
    $task = MaintenanceTask::factory()->interval(6, MaintenanceIntervalUnit::Weeks)->create();

    expect($task->fresh())
        ->schedule_type->toBe(MaintenanceScheduleType::Interval)
        ->interval_unit->toBe(MaintenanceIntervalUnit::Weeks)
        ->interval_value->toBe(6);
});

it('defaults new users to digest opt-in', function () {
    $user = User::factory()->create();

    expect($user->fresh()->maintenance_digest_opt_in)->toBeTrue();
});

it('seeds demo maintenance data on a fresh install', function () {
    $this->seed();

    // One interval task (descale), one calendar task (spring service), one
    // task-bound entry and one ad-hoc entry — see DatabaseSeeder.
    expect(MaintenanceTask::count())->toBe(2)
        ->and(MaintenanceEntry::count())->toBe(2)
        ->and(MaintenanceEntry::whereNull('maintenance_task_id')->count())->toBe(1)
        ->and(MaintenanceTask::query()->active()->whereNotNull('next_due_at')->count())->toBe(2);
});
