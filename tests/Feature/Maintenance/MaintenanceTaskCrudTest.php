<?php

declare(strict_types=1);

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->item = Item::factory()->create();
});

describe('store', function () {
    it('creates an interval task with a derived due date and logs activity', function () {
        $response = $this->actingAs($this->user)->post(route('items.maintenance-tasks.store', $this->item), [
            'title' => 'Change batteries (2x AA)',
            'schedule_type' => 'interval',
            'interval_value' => 6,
            'interval_unit' => 'months',
        ]);

        $response->assertRedirect();
        $task = MaintenanceTask::sole();
        expect($task->title)->toBe('Change batteries (2x AA)')
            ->and($task->schedule_type)->toBe(MaintenanceScheduleType::Interval)
            ->and($task->next_due_at->toDateString())->toBe(today()->addMonthsNoOverflow(6)->toDateString())
            ->and($task->reminder_lead_days)->toBe(7)
            ->and($task->is_active)->toBeTrue();

        $activity = Activity::where('event', 'maintenance_task_added')->sole();
        expect($activity->subject_id)->toBe($this->item->id)
            ->and($activity->properties->get('task_title'))->toBe('Change batteries (2x AA)');
    });

    it('creates a calendar task from a preset payload', function () {
        $this->travelTo('2026-01-15');

        $this->actingAs($this->user)->post(route('items.maintenance-tasks.store', $this->item), [
            'title' => 'Spring service',
            'schedule_type' => 'calendar',
            'schedule_preset' => ['preset' => 'yearly_on', 'month' => 4, 'day' => 1],
            'reminder_lead_days' => 14,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $task = MaintenanceTask::sole();
        expect($task->rrule)->toBe('FREQ=YEARLY;BYMONTH=4;BYMONTHDAY=1')
            ->and($task->next_due_at->toDateString())->toBe('2026-04-01')
            ->and($task->reminder_lead_days)->toBe(14);
    });

    it('creates a one-off task with the given due date', function () {
        $dueAt = today()->addDays(10)->toDateString();

        $this->actingAs($this->user)->post(route('items.maintenance-tasks.store', $this->item), [
            'title' => 'Replace water filter',
            'schedule_type' => 'one_off',
            'next_due_at' => $dueAt,
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect(MaintenanceTask::sole()->next_due_at->toDateString())->toBe($dueAt);
    });

    it('validates per schedule type', function (array $payload, string $errorField) {
        $this->actingAs($this->user)
            ->from(route('items.show', $this->item))
            ->post(route('items.maintenance-tasks.store', $this->item), $payload)
            ->assertSessionHasErrors($errorField);

        expect(MaintenanceTask::count())->toBe(0);
    })->with([
        'missing title' => [['schedule_type' => 'interval', 'interval_value' => 1, 'interval_unit' => 'weeks'], 'title'],
        'interval without value' => [['title' => 'T', 'schedule_type' => 'interval', 'interval_unit' => 'weeks'], 'interval_value'],
        'interval without unit' => [['title' => 'T', 'schedule_type' => 'interval', 'interval_value' => 2], 'interval_unit'],
        'calendar without preset' => [['title' => 'T', 'schedule_type' => 'calendar'], 'schedule_preset'],
        'unknown preset' => [['title' => 'T', 'schedule_type' => 'calendar', 'schedule_preset' => ['preset' => 'cron']], 'schedule_preset.preset'],
        'impossible date (Apr 31)' => [['title' => 'T', 'schedule_type' => 'calendar', 'schedule_preset' => ['preset' => 'yearly_on', 'month' => 4, 'day' => 31]], 'schedule_preset.day'],
        'one-off without date' => [['title' => 'T', 'schedule_type' => 'one_off'], 'next_due_at'],
        'lead days out of range' => [['title' => 'T', 'schedule_type' => 'one_off', 'next_due_at' => '2030-01-01', 'reminder_lead_days' => 999], 'reminder_lead_days'],
        // Forecast tasks are system-managed; users may not create one.
        'forecast not user-pickable' => [['title' => 'T', 'schedule_type' => 'forecast'], 'schedule_type'],
    ]);

    it('allows Feb 29 — a leap-year-only schedule is legitimate', function () {
        $this->actingAs($this->user)->post(route('items.maintenance-tasks.store', $this->item), [
            'title' => 'Leap-day ritual',
            'schedule_type' => 'calendar',
            'schedule_preset' => ['preset' => 'yearly_on', 'month' => 2, 'day' => 29],
        ])->assertSessionHasNoErrors();

        expect(MaintenanceTask::sole()->rrule)->toBe('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=29');
    });

    it('requires authentication', function () {
        $this->post(route('items.maintenance-tasks.store', $this->item), [])
            ->assertRedirect(route('login'));
    });
});

describe('update', function () {
    it('re-derives the due date from the existing anchor when the rule changes', function () {
        $task = MaintenanceTask::factory()->for($this->item)->interval(6, MaintenanceIntervalUnit::Months)->create([
            'last_completed_at' => today()->subMonths(2),
            'next_due_at' => today()->addMonthsNoOverflow(4),
        ]);

        $this->actingAs($this->user)->patch(route('items.maintenance-tasks.update', [$this->item, $task]), [
            'title' => $task->title,
            'schedule_type' => 'interval',
            'interval_value' => 3,
            'interval_unit' => 'months',
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($task->fresh()->next_due_at->toDateString())
            ->toBe(today()->subMonths(2)->addMonthsNoOverflow(3)->toDateString());
    });

    it('keeps the due date on a title-only edit', function () {
        // A calendar task whose occurrence was skipped ahead — editing the
        // title must NOT pull the due date back to the nearer occurrence.
        $skippedAhead = today()->addMonths(6);
        $task = MaintenanceTask::factory()->for($this->item)->calendar('FREQ=MONTHLY;BYDAY=-1FR')->create([
            'next_due_at' => $skippedAhead,
        ]);

        $this->actingAs($this->user)->patch(route('items.maintenance-tasks.update', [$this->item, $task]), [
            'title' => 'Renamed',
            'schedule_type' => 'calendar',
            // no schedule_preset: stored rrule is kept
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($task->fresh())
            ->title->toBe('Renamed')
            ->rrule->toBe('FREQ=MONTHLY;BYDAY=-1FR')
            ->next_due_at->toDateString()->toBe($skippedAhead->toDateString());
    });

    it('switches schedule type and clears the stale rule fields', function () {
        $task = MaintenanceTask::factory()->for($this->item)->interval()->create();

        $this->actingAs($this->user)->patch(route('items.maintenance-tasks.update', [$this->item, $task]), [
            'title' => $task->title,
            'schedule_type' => 'one_off',
            'next_due_at' => today()->addDays(5)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($task->fresh())
            ->schedule_type->toBe(MaintenanceScheduleType::OneOff)
            ->interval_value->toBeNull()
            ->interval_unit->toBeNull()
            ->next_due_at->toDateString()->toBe(today()->addDays(5)->toDateString());
    });

    it('still requires a preset when a non-calendar task becomes calendar', function () {
        $task = MaintenanceTask::factory()->for($this->item)->interval()->create();

        $this->actingAs($this->user)
            ->from(route('items.show', $this->item))
            ->patch(route('items.maintenance-tasks.update', [$this->item, $task]), [
                'title' => $task->title,
                'schedule_type' => 'calendar',
            ])->assertSessionHasErrors('schedule_preset');
    });

    it('404s when the task belongs to a different item', function () {
        $otherTask = MaintenanceTask::factory()->create();

        $this->actingAs($this->user)->patch(route('items.maintenance-tasks.update', [$this->item, $otherTask]), [
            'title' => 'Hijack',
            'schedule_type' => 'one_off',
            'next_due_at' => '2030-01-01',
        ])->assertNotFound();
    });
});

describe('destroy', function () {
    it('deletes the task but keeps its entries as ad-hoc history, and logs the removal', function () {
        $task = MaintenanceTask::factory()->for($this->item)->create(['title' => 'Descale']);
        $entry = MaintenanceEntry::factory()->forTask($task)->create();

        $this->actingAs($this->user)
            ->delete(route('items.maintenance-tasks.destroy', [$this->item, $task]))
            ->assertRedirect();

        expect(MaintenanceTask::count())->toBe(0)
            ->and($entry->fresh())->maintenance_task_id->toBeNull();

        $activity = Activity::where('event', 'maintenance_task_deleted')->sole();
        expect($activity->subject_id)->toBe($this->item->id)
            ->and($activity->properties->get('task_title'))->toBe('Descale');
    });

    it('404s when the task belongs to a different item', function () {
        $otherTask = MaintenanceTask::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('items.maintenance-tasks.destroy', [$this->item, $otherTask]))
            ->assertNotFound();

        expect(MaintenanceTask::count())->toBe(1);
    });
});
