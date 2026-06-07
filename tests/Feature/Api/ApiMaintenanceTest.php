<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MaintenanceIntervalUnit;
use App\Models\Item;
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
}
