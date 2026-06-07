<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_an_items_tasks_requires_authentication(): void
    {
        $item = Item::factory()->create();

        $this->getJson("/api/v1/items/{$item->id}/maintenance-tasks")->assertUnauthorized();
    }

    public function test_lists_the_items_maintenance_tasks_with_due_state(): void
    {
        $item = Item::factory()->create();
        $task = MaintenanceTask::factory()->for($item)
            ->interval(3, MaintenanceIntervalUnit::Months)->overdue(4)
            ->create(['title' => 'Descale']);
        // A task on another item must not leak in.
        MaintenanceTask::factory()->create(['title' => 'Other item task']);

        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson("/api/v1/items/{$item->id}/maintenance-tasks")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $task->id)
            ->assertJsonPath('data.0.title', 'Descale')
            ->assertJsonPath('data.0.schedule_type', 'interval')
            ->assertJsonPath('data.0.interval_value', 3)
            ->assertJsonPath('data.0.interval_unit', 'months')
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('data.0.due_in_days', -4)
            ->assertJsonPath('data.0.next_due_at', today()->subDays(4)->toDateString());
    }

    public function test_listing_requires_the_read_ability(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['write']);

        $this->getJson("/api/v1/items/{$item->id}/maintenance-tasks")->assertForbidden();
    }

    public function test_creates_an_interval_task(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['write']);

        $this->postJson("/api/v1/items/{$item->id}/maintenance-tasks", [
            'title' => 'Descale',
            'schedule_type' => 'interval',
            'interval_value' => 3,
            'interval_unit' => 'months',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Descale')
            ->assertJsonPath('data.interval_value', 3)
            // No completion yet, so the rule counts from today.
            ->assertJsonPath('data.next_due_at', today()->addMonthsNoOverflow(3)->toDateString());

        $task = MaintenanceTask::sole();
        expect($task->item_id)->toBe($item->id)
            ->and($task->schedule_type)->toBe(MaintenanceScheduleType::Interval)
            ->and($task->reminder_lead_days)->toBe(7);
    }

    public function test_creates_a_one_off_task(): void
    {
        $item = Item::factory()->create();
        $dueAt = today()->addDays(30)->toDateString();
        Sanctum::actingAs(User::factory()->create(), ['write']);

        $this->postJson("/api/v1/items/{$item->id}/maintenance-tasks", [
            'title' => 'Winter tyres',
            'schedule_type' => 'one_off',
            'next_due_at' => $dueAt,
        ])
            ->assertCreated()
            ->assertJsonPath('data.schedule_type', 'one_off')
            ->assertJsonPath('data.next_due_at', $dueAt);
    }

    public function test_rejects_calendar_schedules_and_missing_interval_fields(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['write']);

        // Calendar (RRULE) schedules are web-only.
        $this->postJson("/api/v1/items/{$item->id}/maintenance-tasks", [
            'title' => 'X', 'schedule_type' => 'calendar',
        ])->assertJsonValidationErrors('schedule_type');

        $this->postJson("/api/v1/items/{$item->id}/maintenance-tasks", [
            'title' => 'X', 'schedule_type' => 'interval',
        ])->assertJsonValidationErrors(['interval_value', 'interval_unit']);
    }

    public function test_creating_requires_the_write_ability(): void
    {
        $item = Item::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->postJson("/api/v1/items/{$item->id}/maintenance-tasks", [
            'title' => 'X', 'schedule_type' => 'one_off', 'next_due_at' => today()->toDateString(),
        ])->assertForbidden();
    }

    public function test_completes_a_task_and_rolls_the_schedule(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();
        $task = MaintenanceTask::factory()->for($item)
            ->interval(1, MaintenanceIntervalUnit::Months)->overdue(5)
            ->create();

        Sanctum::actingAs($user, ['write']);

        $this->postJson("/api/v1/maintenance-tasks/{$task->id}/complete", [
            'notes' => 'Used citric acid.',
            'cost' => 4.5,
        ])
            ->assertOk()
            ->assertJsonPath('data.last_completed_at', today()->toDateString())
            ->assertJsonPath('data.next_due_at', today()->addMonthNoOverflow()->toDateString());

        $entry = MaintenanceEntry::sole();
        expect($entry->performed_by)->toBe($user->id)
            ->and($entry->cost)->toBe('4.50')
            ->and($entry->notes)->toBe('Used citric acid.');
    }

    public function test_completing_an_archived_task_fails_validation(): void
    {
        $task = MaintenanceTask::factory()->inactive()->create();
        Sanctum::actingAs(User::factory()->create(), ['write']);

        $this->postJson("/api/v1/maintenance-tasks/{$task->id}/complete")
            ->assertJsonValidationErrors('task');

        expect(MaintenanceEntry::count())->toBe(0);
    }

    public function test_completing_requires_the_write_ability(): void
    {
        $task = MaintenanceTask::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->postJson("/api/v1/maintenance-tasks/{$task->id}/complete")->assertForbidden();
    }
}
