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

it('lists every active task soonest-first with item context', function () {
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    $mower = Item::factory()->create(['name' => 'Lawnmower', 'parent_id' => $garage->id]);

    $later = MaintenanceTask::factory()->for($mower)->dueSoon(30)->create();
    $sooner = MaintenanceTask::factory()->for($mower)->overdue(3)->create();
    MaintenanceTask::factory()->for($mower)->oneOff()->inactive()->create(); // archived: hidden

    $this->actingAs($this->user)->get(route('maintenance'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Maintenance')
            ->where('filter', 'all')
            ->has('tasks', 2)
            ->where('tasks.0.id', $sooner->id)
            ->where('tasks.0.item.name', 'Lawnmower')
            ->where('tasks.0.item.location', 'Garage')
            ->where('tasks.1.id', $later->id)
        );
});

it('builds deep location paths with the batched helper', function () {
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    $toolbox = Item::factory()->container()->create(['name' => 'Toolbox', 'parent_id' => $garage->id]);
    $drill = Item::factory()->create(['name' => 'Drill', 'parent_id' => $toolbox->id]);
    MaintenanceTask::factory()->for($drill)->dueSoon(5)->create();

    $this->actingAs($this->user)->get(route('maintenance'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tasks.0.item.location', 'Garage / Toolbox')
        );
});

it('partitions counts by the same window the badge and digest use', function () {
    MaintenanceTask::factory()->overdue(2)->create();
    MaintenanceTask::factory()->dueSoon(3)->create(['reminder_lead_days' => 7]);  // inside window
    MaintenanceTask::factory()->dueSoon(20)->create(['reminder_lead_days' => 7]); // outside window

    $this->actingAs($this->user)->get(route('maintenance'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts.all', 3)
            ->where('counts.overdue', 1)
            ->where('counts.due_soon', 1)
        );
});

it('filters overdue and due-soon', function () {
    $overdue = MaintenanceTask::factory()->overdue(2)->create();
    $dueSoon = MaintenanceTask::factory()->dueSoon(3)->create(['reminder_lead_days' => 7]);

    $this->actingAs($this->user)->get(route('maintenance', ['filter' => 'overdue']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter', 'overdue')
            ->has('tasks', 1)
            ->where('tasks.0.id', $overdue->id)
        );

    $this->actingAs($this->user)->get(route('maintenance', ['filter' => 'due-soon']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter', 'due-soon')
            ->has('tasks', 1)
            ->where('tasks.0.id', $dueSoon->id)
        );
});

it('surfaces an anomalous active task without a due date, sorted last', function () {
    // The schedule service never produces this state (active ⇒ due date);
    // if manual DB surgery does, the page must show it, not hide it.
    $normal = MaintenanceTask::factory()->dueSoon(3)->create();
    $anomalous = MaintenanceTask::factory()->create(['next_due_at' => null]);

    $this->actingAs($this->user)->get(route('maintenance'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('tasks', 2)
            ->where('tasks.0.id', $normal->id)
            ->where('tasks.1.id', $anomalous->id)
            ->where('tasks.1.next_due_at', null)
            ->where('tasks.1.due_in_days', null)
        );
});

it('falls back to the all filter on unknown values', function () {
    MaintenanceTask::factory()->create();

    $this->actingAs($this->user)->get(route('maintenance', ['filter' => 'bogus']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filter', 'all')
            ->has('tasks', 1)
        );
});

it('requires authentication', function () {
    $this->get(route('maintenance'))->assertRedirect(route('login'));
});
