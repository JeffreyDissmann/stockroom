<?php

declare(strict_types=1);

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

describe('store (ad-hoc)', function () {
    it('records an ad-hoc entry and logs activity', function () {
        $this->actingAs($this->user)->post(route('items.maintenance-entries.store', $this->item), [
            'notes' => 'Replaced both brake pads.',
            'cost' => '24.90',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = MaintenanceEntry::sole();
        expect($entry)
            ->maintenance_task_id->toBeNull()
            ->item_id->toBe($this->item->id)
            ->performed_by->toBe($this->user->id)
            ->completed_at->toDateString()->toBe(today()->toDateString())
            ->cost->toBe('24.90');

        $activity = Activity::where('event', 'maintenance_logged')->sole();
        expect($activity->subject_id)->toBe($this->item->id)
            ->and($activity->properties->get('notes'))->toBe('Replaced both brake pads.');
    });

    it('accepts a backdated entry', function () {
        $completedAt = today()->subMonths(2);

        $this->actingAs($this->user)->post(route('items.maintenance-entries.store', $this->item), [
            'notes' => 'Earlier repair, recorded late.',
            'completed_at' => $completedAt->toDateString(),
        ])->assertSessionHasNoErrors();

        expect(MaintenanceEntry::sole()->completed_at->toDateString())->toBe($completedAt->toDateString());
    });

    it('requires notes — they are the only description of what was done', function () {
        $this->actingAs($this->user)
            ->from(route('items.show', $this->item))
            ->post(route('items.maintenance-entries.store', $this->item), ['cost' => '5'])
            ->assertSessionHasErrors('notes');

        expect(MaintenanceEntry::count())->toBe(0);
    });

    it('rejects a future date', function () {
        $this->actingAs($this->user)
            ->from(route('items.show', $this->item))
            ->post(route('items.maintenance-entries.store', $this->item), [
                'notes' => 'Time travel.',
                'completed_at' => today()->addDay()->toDateString(),
            ])->assertSessionHasErrors('completed_at');
    });
});

describe('destroy', function () {
    it('removes an entry and logs the removal', function () {
        $entry = MaintenanceEntry::factory()->for($this->item)->create(['notes' => 'Old repair record.']);

        $this->actingAs($this->user)
            ->delete(route('items.maintenance-entries.destroy', [$this->item, $entry]))
            ->assertRedirect();

        expect(MaintenanceEntry::count())->toBe(0);

        // Removing history is itself history.
        $activity = Activity::where('event', 'maintenance_entry_deleted')->sole();
        expect($activity->subject_id)->toBe($this->item->id)
            ->and($activity->properties->get('notes'))->toBe('Old repair record.');
    });

    it('logs the task title when deleting a completion entry', function () {
        $task = MaintenanceTask::factory()->for($this->item)->create(['title' => 'Descale']);
        $entry = MaintenanceEntry::factory()->forTask($task)->create();

        $this->actingAs($this->user)
            ->delete(route('items.maintenance-entries.destroy', [$this->item, $entry]))
            ->assertRedirect();

        expect(Activity::where('event', 'maintenance_entry_deleted')->sole()->properties->get('task_title'))
            ->toBe('Descale');
    });

    it('leaves the task schedule untouched when deleting a completion entry', function () {
        $task = MaintenanceTask::factory()->for($this->item)->create([
            'last_completed_at' => today()->subWeek(),
        ]);
        $entry = MaintenanceEntry::factory()->forTask($task)->create();
        $dueAt = $task->next_due_at->toDateString();

        $this->actingAs($this->user)
            ->delete(route('items.maintenance-entries.destroy', [$this->item, $entry]))
            ->assertRedirect();

        // Entries are history; the schedule's state is not derived from
        // them after the fact.
        expect($task->fresh())
            ->next_due_at->toDateString()->toBe($dueAt)
            ->last_completed_at->toDateString()->toBe(today()->subWeek()->toDateString());
    });

    it('404s on an entry of another item', function () {
        $otherEntry = MaintenanceEntry::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('items.maintenance-entries.destroy', [$this->item, $otherEntry]))
            ->assertNotFound();

        expect(MaintenanceEntry::count())->toBe(1);
    });
});

it('requires authentication', function () {
    $entry = MaintenanceEntry::factory()->for($this->item)->create();

    $this->post(route('items.maintenance-entries.store', $this->item), [])->assertRedirect(route('login'));
    $this->delete(route('items.maintenance-entries.destroy', [$this->item, $entry]))->assertRedirect(route('login'));
});
