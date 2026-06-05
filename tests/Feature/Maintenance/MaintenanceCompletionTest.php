<?php

declare(strict_types=1);

use App\Enums\MaintenanceIntervalUnit;
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

describe('complete', function () {
    it('records an entry and rolls an interval task forward', function () {
        $task = MaintenanceTask::factory()->for($this->item)->interval(1, MaintenanceIntervalUnit::Months)->overdue(5)->create();

        $this->actingAs($this->user)->post(route('items.maintenance-tasks.complete', [$this->item, $task]), [
            'notes' => 'Used the citric acid solution.',
            'cost' => '4.50',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = MaintenanceEntry::sole();
        expect($entry)
            ->maintenance_task_id->toBe($task->id)
            ->item_id->toBe($this->item->id)
            ->performed_by->toBe($this->user->id)
            ->completed_at->toDateString()->toBe(today()->toDateString())
            ->notes->toBe('Used the citric acid solution.')
            ->cost->toBe('4.50');

        expect($task->fresh())
            ->last_completed_at->toDateString()->toBe(today()->toDateString())
            ->next_due_at->toDateString()->toBe(today()->addMonthNoOverflow()->toDateString());

        $activity = Activity::where('event', 'maintenance_completed')->sole();
        expect($activity->subject_id)->toBe($this->item->id)
            ->and($activity->properties->get('task_title'))->toBe($task->title)
            ->and($activity->properties->get('cost'))->toBe('4.50');
    });

    it('rolls forward from a backdated completion date', function () {
        $task = MaintenanceTask::factory()->for($this->item)->interval(2, MaintenanceIntervalUnit::Weeks)->create();
        $completedAt = today()->subDays(4);

        $this->actingAs($this->user)->post(route('items.maintenance-tasks.complete', [$this->item, $task]), [
            'completed_at' => $completedAt->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($task->fresh()->next_due_at->toDateString())
            ->toBe($completedAt->addWeeks(2)->toDateString());
    });

    it('archives a one-off on completion', function () {
        $task = MaintenanceTask::factory()->for($this->item)->oneOff()->dueSoon()->create();

        $this->actingAs($this->user)->post(route('items.maintenance-tasks.complete', [$this->item, $task]))
            ->assertRedirect();

        expect($task->fresh())
            ->is_active->toBeFalse()
            ->next_due_at->toBeNull()
            ->and(MaintenanceEntry::count())->toBe(1);
    });

    it('rejects a future completion date', function () {
        $task = MaintenanceTask::factory()->for($this->item)->create();

        $this->actingAs($this->user)
            ->from(route('items.show', $this->item))
            ->post(route('items.maintenance-tasks.complete', [$this->item, $task]), [
                'completed_at' => today()->addDay()->toDateString(),
            ])->assertSessionHasErrors('completed_at');

        expect(MaintenanceEntry::count())->toBe(0);
    });

    it('rejects an archived task with a validation error', function () {
        $task = MaintenanceTask::factory()->for($this->item)->oneOff()->inactive()->create();

        $this->actingAs($this->user)
            ->from(route('items.show', $this->item))
            ->post(route('items.maintenance-tasks.complete', [$this->item, $task]))
            ->assertRedirect(route('items.show', $this->item))
            ->assertSessionHasErrors('task');

        expect(MaintenanceEntry::count())->toBe(0);
    });

    it('404s on a task of another item', function () {
        $otherTask = MaintenanceTask::factory()->create();

        $this->actingAs($this->user)
            ->post(route('items.maintenance-tasks.complete', [$this->item, $otherTask]))
            ->assertNotFound();
    });
});

describe('skip', function () {
    it('advances a calendar task without recording an entry', function () {
        $this->travelTo('2026-01-15');
        $task = MaintenanceTask::factory()->for($this->item)->calendar('FREQ=MONTHLY;BYDAY=-1FR')->create([
            'next_due_at' => '2026-01-30',
            'last_completed_at' => '2025-12-26',
        ]);

        $this->actingAs($this->user)->post(route('items.maintenance-tasks.skip', [$this->item, $task]))
            ->assertRedirect();

        expect($task->fresh())
            ->next_due_at->toDateString()->toBe('2026-02-27')
            ->last_completed_at->toDateString()->toBe('2025-12-26')
            ->and(MaintenanceEntry::count())->toBe(0);

        $activity = Activity::where('event', 'maintenance_skipped')->sole();
        expect($activity->properties->get('task_title'))->toBe($task->title);
    });

    it('rejects a non-calendar task with a validation error', function () {
        $task = MaintenanceTask::factory()->for($this->item)->interval()->create();
        $dueAt = $task->next_due_at->toDateString();

        $this->actingAs($this->user)
            ->post(route('items.maintenance-tasks.skip', [$this->item, $task]))
            ->assertRedirect()
            ->assertSessionHasErrors('task');

        expect($task->fresh()->next_due_at->toDateString())->toBe($dueAt);
    });

    it('rejects an archived task with a validation error', function () {
        $task = MaintenanceTask::factory()->for($this->item)->calendar()->inactive()->create();

        $this->actingAs($this->user)
            ->post(route('items.maintenance-tasks.skip', [$this->item, $task]))
            ->assertRedirect()
            ->assertSessionHasErrors('task');
    });
});

it('requires authentication for both actions', function () {
    $task = MaintenanceTask::factory()->for($this->item)->create();

    $this->post(route('items.maintenance-tasks.complete', [$this->item, $task]))->assertRedirect(route('login'));
    $this->post(route('items.maintenance-tasks.skip', [$this->item, $task]))->assertRedirect(route('login'));
});
