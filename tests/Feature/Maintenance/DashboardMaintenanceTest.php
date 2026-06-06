<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('ships the five most urgent attention-needing tasks with the total count', function () {
    $item = Item::factory()->create(['name' => 'Boiler']);

    // Six tasks needing attention (overdue then in-window) + one future.
    $mostUrgent = MaintenanceTask::factory()->for($item)->overdue(10)->create();
    MaintenanceTask::factory()->for($item)->overdue(5)->create();
    MaintenanceTask::factory()->for($item)->overdue(2)->create();
    MaintenanceTask::factory()->for($item)->dueSoon(1)->create(['reminder_lead_days' => 7]);
    MaintenanceTask::factory()->for($item)->dueSoon(3)->create(['reminder_lead_days' => 7]);
    MaintenanceTask::factory()->for($item)->dueSoon(5)->create(['reminder_lead_days' => 7]);
    MaintenanceTask::factory()->for($item)->dueSoon(60)->create(['reminder_lead_days' => 7]); // future: excluded

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('maintenance.count', 6)
            ->has('maintenance.tasks', 5)
            ->where('maintenance.tasks.0.id', $mostUrgent->id)
            ->where('maintenance.tasks.0.is_overdue', true)
            ->where('maintenance.tasks.0.item.name', 'Boiler')
        );
});

it('ships an empty payload when nothing needs attention', function () {
    MaintenanceTask::factory()->dueSoon(60)->create(['reminder_lead_days' => 7]);
    MaintenanceTask::factory()->oneOff()->inactive()->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('maintenance.count', 0)
            ->has('maintenance.tasks', 0)
        );
});
