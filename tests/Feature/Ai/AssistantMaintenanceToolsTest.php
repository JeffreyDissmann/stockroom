<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\MaintenanceOverview;
use App\Models\Item;
use App\Models\MaintenanceTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
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
