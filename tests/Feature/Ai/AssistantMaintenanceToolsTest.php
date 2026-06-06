<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\CompleteMaintenanceTask;
use App\Ai\Tools\CreateMaintenanceTask;
use App\Ai\Tools\GetItem;
use App\Ai\Tools\LogMaintenanceEntry;
use App\Ai\Tools\MaintenanceOverview;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AssistantMaintenanceToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_overview_defaults_to_tasks_needing_attention(): void
    {
        $heating = Item::factory()->create(['name' => 'Heating']);
        $detector = Item::factory()->create(['name' => 'Smoke Detector']);

        MaintenanceTask::factory()->overdue(5)->for($heating)->create(['title' => 'Annual service']);
        // dueSoon(3) sits inside the default 7-day reminder window.
        MaintenanceTask::factory()->dueSoon(3)->for($detector)->create(['title' => 'Replace batteries']);
        // Factory default is due in a month — outside the reminder window.
        MaintenanceTask::factory()->create(['title' => 'Descale machine']);

        $result = app(MaintenanceOverview::class)->handle(new Request([]));

        $this->assertStringContainsString('Annual service', $result);
        $this->assertStringContainsString('Replace batteries', $result);
        $this->assertStringNotContainsString('Descale machine', $result);
    }

    public function test_maintenance_overview_links_items_and_reports_due_dates(): void
    {
        $item = Item::factory()->create(['name' => 'Heating']);
        $task = MaintenanceTask::factory()->overdue(5)->for($item)->create(['title' => 'Annual service']);

        $result = app(MaintenanceOverview::class)->handle(new Request([]));

        $this->assertStringContainsString("Task #{$task->id}", $result);
        $this->assertStringContainsString("[Heating](/items/{$item->id})", $result);
        $this->assertStringContainsString(today()->subDays(5)->toDateString(), $result);
        $this->assertStringContainsString('last completed: never', $result);
    }

    public function test_maintenance_overview_scope_all_lists_every_active_schedule(): void
    {
        MaintenanceTask::factory()->create(['title' => 'Descale machine']);
        MaintenanceTask::factory()->inactive()->create(['title' => 'Old chore']);

        $result = app(MaintenanceOverview::class)->handle(new Request(['scope' => 'all']));

        $this->assertStringContainsString('Descale machine', $result);
        $this->assertStringNotContainsString('Old chore', $result);
    }

    public function test_get_item_lists_active_schedules_and_recent_history(): void
    {
        $item = Item::factory()->create(['name' => 'Heating']);
        $task = MaintenanceTask::factory()->for($item)->create(['title' => 'Annual service']);
        // Archived (completed one-off) schedules are history, not plans.
        MaintenanceTask::factory()->inactive()->for($item)->create(['title' => 'Install filter']);

        $user = User::factory()->create(['name' => 'Jeff']);
        MaintenanceEntry::factory()->forTask($task)->create([
            'performed_by' => $user->id,
            'completed_at' => '2026-05-01',
            'notes' => 'Replaced the seal too.',
            'cost' => 120,
        ]);

        $result = app(GetItem::class)->handle(new Request(['id' => $item->id]));

        $this->assertStringContainsString("Task #{$task->id} \"Annual service\"", $result);
        $this->assertStringContainsString($task->next_due_at->toDateString(), $result);
        $this->assertStringNotContainsString('Install filter', $result);
        $this->assertStringContainsString('2026-05-01: Annual service; by Jeff; cost 120', $result);
        $this->assertStringContainsString('Replaced the seal too.', $result);
    }

    public function test_get_item_caps_history_at_the_five_newest_entries(): void
    {
        $item = Item::factory()->create();

        foreach (range(1, 6) as $day) {
            MaintenanceEntry::factory()->for($item)->create([
                'completed_at' => "2026-03-0{$day}",
                'notes' => "entry-{$day}",
            ]);
        }

        $result = app(GetItem::class)->handle(new Request(['id' => $item->id]));

        $this->assertStringContainsString('entry-6', $result);
        $this->assertStringContainsString('entry-2', $result);
        $this->assertStringNotContainsString('entry-1', $result);
    }

    public function test_get_item_omits_maintenance_sections_when_there_is_nothing(): void
    {
        $item = Item::factory()->create();

        $result = app(GetItem::class)->handle(new Request(['id' => $item->id]));

        $this->assertStringNotContainsString('Maintenance schedules:', $result);
        $this->assertStringNotContainsString('maintenance history', $result);
    }

    public function test_complete_maintenance_task_records_entry_and_rolls_the_schedule(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = Item::factory()->create(['name' => 'Coffee Machine']);
        $task = MaintenanceTask::factory()->for($item)
            ->interval(1, MaintenanceIntervalUnit::Months)->overdue(5)
            ->create(['title' => 'Descale']);

        $result = app(CompleteMaintenanceTask::class)->handle(new Request([
            'task_id' => $task->id,
            'notes' => 'Used the citric acid solution.',
            'cost' => 4.5,
        ]));

        $entry = MaintenanceEntry::sole();
        $this->assertSame($task->id, $entry->maintenance_task_id);
        $this->assertSame($user->id, $entry->performed_by);
        $this->assertSame(today()->toDateString(), $entry->completed_at->toDateString());
        $this->assertSame('Used the citric acid solution.', $entry->notes);
        $this->assertSame('4.50', $entry->cost);

        $this->assertSame(
            today()->addMonthNoOverflow()->toDateString(),
            $task->fresh()->next_due_at->toDateString(),
        );

        $activity = Activity::where('event', 'maintenance_completed')->sole();
        $this->assertSame($item->id, $activity->subject_id);
        $this->assertSame('Descale', $activity->properties->get('task_title'));

        $this->assertStringContainsString("[Coffee Machine](/items/{$item->id})", $result);
        $this->assertStringContainsString('Next due: '.today()->addMonthNoOverflow()->toDateString(), $result);
    }

    public function test_complete_maintenance_task_rolls_forward_from_a_backdated_date(): void
    {
        $this->actingAs(User::factory()->create());
        $task = MaintenanceTask::factory()->interval(2, MaintenanceIntervalUnit::Weeks)->create();

        app(CompleteMaintenanceTask::class)->handle(new Request([
            'task_id' => $task->id,
            'completed_at' => today()->subDays(4)->toDateString(),
        ]));

        $this->assertSame(
            today()->subDays(4)->addWeeks(2)->toDateString(),
            $task->fresh()->next_due_at->toDateString(),
        );
    }

    public function test_complete_maintenance_task_archives_a_one_off(): void
    {
        $this->actingAs(User::factory()->create());
        $task = MaintenanceTask::factory()->oneOff()->dueSoon(3)->create();

        $result = app(CompleteMaintenanceTask::class)->handle(new Request(['task_id' => $task->id]));

        $this->assertFalse($task->fresh()->is_active);
        $this->assertStringContainsString('archived', $result);
    }

    public function test_complete_maintenance_task_rejects_invalid_input_without_writing(): void
    {
        $this->actingAs(User::factory()->create());
        $task = MaintenanceTask::factory()->create();
        $archived = MaintenanceTask::factory()->inactive()->create();
        $tool = app(CompleteMaintenanceTask::class);

        $this->assertStringContainsString('No maintenance task found', $tool->handle(new Request(['task_id' => 999999])));
        $this->assertStringContainsString('cannot be completed again', $tool->handle(new Request(['task_id' => $archived->id])));
        $this->assertStringContainsString('future', $tool->handle(new Request([
            'task_id' => $task->id,
            'completed_at' => today()->addDay()->toDateString(),
        ])));
        $this->assertStringContainsString('could not be parsed', $tool->handle(new Request([
            'task_id' => $task->id,
            'completed_at' => 'not-a-date',
        ])));
        $this->assertStringContainsString('non-negative number', $tool->handle(new Request([
            'task_id' => $task->id,
            'cost' => -5,
        ])));

        $this->assertSame(0, MaintenanceEntry::count());
    }

    public function test_create_maintenance_task_creates_an_interval_schedule(): void
    {
        $item = Item::factory()->create(['name' => 'Coffee Machine']);

        $result = app(CreateMaintenanceTask::class)->handle(new Request([
            'item_id' => $item->id,
            'title' => 'Descale',
            'schedule_type' => 'interval',
            'interval_value' => 3,
            'interval_unit' => 'months',
        ]));

        $task = MaintenanceTask::sole();
        $this->assertSame('Descale', $task->title);
        $this->assertSame(MaintenanceScheduleType::Interval, $task->schedule_type);
        $this->assertSame(3, $task->interval_value);
        $this->assertSame(MaintenanceIntervalUnit::Months, $task->interval_unit);
        $this->assertSame(7, $task->reminder_lead_days);
        // No completion yet, so the rule starts counting today.
        $this->assertSame(today()->addMonthsNoOverflow(3)->toDateString(), $task->next_due_at->toDateString());

        $activity = Activity::where('event', 'maintenance_task_added')->sole();
        $this->assertSame('Descale', $activity->properties->get('task_title'));

        $this->assertStringContainsString("Created task #{$task->id}", $result);
        $this->assertStringContainsString("[Coffee Machine](/items/{$item->id})", $result);
        $this->assertStringContainsString($task->next_due_at->toDateString(), $result);
    }

    public function test_create_maintenance_task_creates_a_one_off(): void
    {
        $item = Item::factory()->create();
        $dueAt = today()->addDays(30)->toDateString();

        app(CreateMaintenanceTask::class)->handle(new Request([
            'item_id' => $item->id,
            'title' => 'Install the winter tyres',
            'schedule_type' => 'one_off',
            'next_due_at' => $dueAt,
            'reminder_lead_days' => 14,
        ]));

        $task = MaintenanceTask::sole();
        $this->assertSame(MaintenanceScheduleType::OneOff, $task->schedule_type);
        $this->assertSame($dueAt, $task->next_due_at->toDateString());
        $this->assertSame(14, $task->reminder_lead_days);
        $this->assertTrue($task->is_active);
    }

    public function test_create_maintenance_task_rejects_invalid_input_without_writing(): void
    {
        $item = Item::factory()->create();
        $tool = app(CreateMaintenanceTask::class);
        $base = ['item_id' => $item->id, 'title' => 'Descale', 'schedule_type' => 'interval', 'interval_value' => 3, 'interval_unit' => 'months'];

        $this->assertStringContainsString('No item found', $tool->handle(new Request([...$base, 'item_id' => 999999])));
        $this->assertStringContainsString('title', $tool->handle(new Request([...$base, 'title' => ' '])));
        // Calendar rules are deliberately UI-only.
        $this->assertStringContainsString('item page', $tool->handle(new Request([...$base, 'schedule_type' => 'calendar'])));
        $this->assertStringContainsString('interval_value', $tool->handle(new Request([...$base, 'interval_value' => 0])));
        $this->assertStringContainsString('interval_unit', $tool->handle(new Request([...$base, 'interval_unit' => 'fortnights'])));
        $this->assertStringContainsString('reminder_lead_days', $tool->handle(new Request([...$base, 'reminder_lead_days' => 9999])));
        $this->assertStringContainsString('next_due_at', $tool->handle(new Request([
            'item_id' => $item->id, 'title' => 'Once', 'schedule_type' => 'one_off', 'next_due_at' => 'not-a-date',
        ])));

        $this->assertSame(0, MaintenanceTask::count());
    }

    public function test_log_maintenance_entry_records_an_ad_hoc_repair(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $item = Item::factory()->create(['name' => 'Dresser']);

        $result = app(LogMaintenanceEntry::class)->handle(new Request([
            'item_id' => $item->id,
            'notes' => 'Repaired the drawer handle.',
            'completed_at' => today()->subDays(2)->toDateString(),
            'cost' => 3.2,
        ]));

        $entry = MaintenanceEntry::sole();
        $this->assertNull($entry->maintenance_task_id);
        $this->assertSame($user->id, $entry->performed_by);
        $this->assertSame(today()->subDays(2)->toDateString(), $entry->completed_at->toDateString());
        $this->assertSame('Repaired the drawer handle.', $entry->notes);
        $this->assertSame('3.20', $entry->cost);

        $activity = Activity::where('event', 'maintenance_logged')->sole();
        $this->assertSame('Repaired the drawer handle.', $activity->properties->get('notes'));

        $this->assertStringContainsString("[Dresser](/items/{$item->id})", $result);
        $this->assertStringContainsString('Repaired the drawer handle.', $result);
    }

    public function test_log_maintenance_entry_rejects_invalid_input_without_writing(): void
    {
        $this->actingAs(User::factory()->create());
        $item = Item::factory()->create();
        $tool = app(LogMaintenanceEntry::class);
        $base = ['item_id' => $item->id, 'notes' => 'Repaired the hinge.'];

        $this->assertStringContainsString('No item found', $tool->handle(new Request([...$base, 'item_id' => 999999])));
        // Without a task title the notes are the only record of what was done.
        $this->assertStringContainsString('Notes', $tool->handle(new Request([...$base, 'notes' => ' '])));
        $this->assertStringContainsString('future', $tool->handle(new Request([...$base, 'completed_at' => today()->addDay()->toDateString()])));
        $this->assertStringContainsString('non-negative number', $tool->handle(new Request([...$base, 'cost' => -1])));

        $this->assertSame(0, MaintenanceEntry::count());
    }

    public function test_maintenance_overview_reports_empty_states_per_scope(): void
    {
        // A healthy schedule exists, but nothing needs attention.
        MaintenanceTask::factory()->create(['title' => 'Descale machine']);

        $attention = app(MaintenanceOverview::class)->handle(new Request([]));
        $this->assertStringContainsString('No maintenance needs attention', $attention);

        MaintenanceTask::query()->delete();

        $all = app(MaintenanceOverview::class)->handle(new Request(['scope' => 'all']));
        $this->assertStringContainsString('No active maintenance schedules', $all);
    }
}
