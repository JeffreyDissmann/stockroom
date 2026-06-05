<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->item = Item::factory()->create();
});

it('ships active tasks soonest-first and history newest-first', function () {
    $later = MaintenanceTask::factory()->for($this->item)->dueSoon(20)->create();
    $sooner = MaintenanceTask::factory()->for($this->item)->overdue(2)->create();
    MaintenanceTask::factory()->for($this->item)->oneOff()->inactive()->create(); // archived: hidden
    MaintenanceTask::factory()->create(); // other item's task: hidden

    $old = MaintenanceEntry::factory()->for($this->item)->create(['completed_at' => today()->subYear()]);
    $recent = MaintenanceEntry::factory()->forTask($sooner)->create(['completed_at' => today()->subDay()]);

    $this->actingAs($this->user)->get(route('items.show', $this->item))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('items/Show')
            ->has('maintenance.tasks', 2)
            ->where('maintenance.tasks.0.id', $sooner->id)
            ->where('maintenance.tasks.0.is_overdue', true)
            ->where('maintenance.tasks.1.id', $later->id)
            ->has('maintenance.entries', 2)
            ->where('maintenance.entries.0.id', $recent->id)
            ->where('maintenance.entries.0.task_title', $sooner->title)
            ->where('maintenance.entries.1.id', $old->id)
            ->where('maintenance.entries.1.task_title', null)
        );
});

it('ships the fields the task cards and dialogs need', function () {
    MaintenanceTask::factory()->for($this->item)->interval(6)->dueSoon(5)->create([
        'title' => 'Change batteries',
        'reminder_lead_days' => 3,
    ]);

    $this->actingAs($this->user)->get(route('items.show', $this->item))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('maintenance.tasks.0.title', 'Change batteries')
            ->where('maintenance.tasks.0.schedule_type', 'interval')
            ->where('maintenance.tasks.0.schedule_summary', 'Every 6 months after completion')
            ->where('maintenance.tasks.0.due_in_days', 5)
            ->where('maintenance.tasks.0.can_skip', false)
            ->where('maintenance.tasks.0.reminder_lead_days', 3)
            ->where('maintenance.tasks.0.interval_value', 6)
            ->where('maintenance.tasks.0.interval_unit', 'months')
        );
});

it('ships empty collections when an item has no maintenance', function () {
    $this->actingAs($this->user)->get(route('items.show', $this->item))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('maintenance.tasks', 0)
            ->has('maintenance.entries', 0)
        );
});

it('presents maintenance activity rows with their task title', function () {
    $task = MaintenanceTask::factory()->for($this->item)->create(['title' => 'Descale']);
    $this->actingAs($this->user)->post(route('items.maintenance-tasks.complete', [$this->item, $task]));
    $this->actingAs($this->user)->post(route('items.maintenance-entries.store', $this->item), [
        'notes' => 'Replaced the drawer handle.',
    ]);

    $this->actingAs($this->user)->get(route('items.show', $this->item))
        ->assertInertia(fn (AssertableInertia $page) => $page
            // Newest first: the ad-hoc log, then the completion.
            ->where('activities.0.event', 'maintenance_logged')
            ->where('activities.0.task_title', 'Replaced the drawer handle.')
            ->where('activities.1.event', 'maintenance_completed')
            ->where('activities.1.task_title', 'Descale')
        );
});
