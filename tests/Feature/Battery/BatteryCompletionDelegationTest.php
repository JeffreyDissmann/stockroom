<?php

declare(strict_types=1);

use App\Ai\Tools\CompleteMaintenanceTask;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\User;
use App\Services\Battery\BatteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A battery-tracked item with its system "Replace battery" forecast task and
 * one open cycle, via the normal recording path.
 */
function batteryTrackedItem(): Item
{
    $item = Item::factory()->create();
    app(BatteryService::class)->recordReading($item, 50, now()->subMonth());

    return $item;
}

function batteryForecastTask(Item $item)
{
    return $item->maintenanceTasks()->where('schedule_type', MaintenanceScheduleType::Forecast)->first();
}

it('swaps the battery when the reminder is completed from the web UI', function () {
    $user = User::factory()->create();
    $item = batteryTrackedItem();
    $task = batteryForecastTask($item);

    $this->actingAs($user)
        ->from(route('items.show', $item))
        ->post(route('items.maintenance-tasks.complete', [$item, $task]), ['notes' => 'new cells'])
        ->assertRedirect(route('items.show', $item))
        ->assertSessionHasNoErrors();

    expect($item->batteryCycles()->count())->toBe(2)
        ->and($item->refresh()->currentBatteryCycle->notes)->toBe('new cells');

    // Completion recorded as a history entry, attributed to the user.
    $entry = MaintenanceEntry::query()->where('maintenance_task_id', $task->id)->sole();
    expect($entry->performed_by)->toBe($user->id);
});

it('swaps the battery when the reminder is completed via the API', function () {
    $item = batteryTrackedItem();
    $task = batteryForecastTask($item);

    Sanctum::actingAs(User::factory()->create(), ['write']);

    $this->postJson("/api/v1/maintenance-tasks/{$task->id}/complete", ['completed_at' => today()->toDateString()])
        ->assertOk()
        ->assertJsonPath('data.schedule_type', 'forecast');

    expect($item->batteryCycles()->count())->toBe(2)
        ->and(MaintenanceEntry::query()->where('maintenance_task_id', $task->id)->count())->toBe(1);
});

it('swaps the battery when the reminder is completed by the assistant', function () {
    $user = User::factory()->create();
    $item = batteryTrackedItem();
    $task = batteryForecastTask($item);

    $this->actingAs($user);

    $result = app(CompleteMaintenanceTask::class)->handle(new Request([
        'task_id' => $task->id,
    ]));

    expect($result)->toContain('battery change')
        ->and($item->batteryCycles()->count())->toBe(2)
        ->and(MaintenanceEntry::query()->where('maintenance_task_id', $task->id)->count())->toBe(1);
});
