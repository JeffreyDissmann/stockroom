<?php

declare(strict_types=1);

use App\Enums\MaintenanceScheduleType;
use App\Jobs\RefreshBatteryForecast;
use App\Models\BatteryCycle;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Services\Battery\BatteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BatteryService::class);
    config([
        'stockroom.battery.low_threshold' => 20,
        'stockroom.battery.reminder_lead_days' => 3,
        'stockroom.battery.change_detection.min_percent' => 90,
        'stockroom.battery.change_detection.min_jump' => 50,
    ]);
});

function forecastTask(Item $item)
{
    return $item->maintenanceTasks()->where('schedule_type', MaintenanceScheduleType::Forecast)->first();
}

it('makes an item battery-tracked on the first reading, creating the forecast task and a cycle', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 80, now());

    expect($item->batteryCycles()->count())->toBe(1)
        ->and(forecastTask($item))->not->toBeNull()
        ->and(forecastTask($item)->reminder_lead_days)->toBe(3);
});

it('reuses the single forecast task across many readings', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 80, now()->subDays(2));
    $this->service->recordReading($item, 70, now());

    expect($item->maintenanceTasks()->where('schedule_type', MaintenanceScheduleType::Forecast)->count())->toBe(1);
});

it('auto-detects a swap on a low→full jump: new cycle, one completion, reading on the fresh battery', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 8, now()->subDay());
    $this->service->recordReading($item, 100, now()); // jump → swap

    $task = forecastTask($item);

    expect($item->batteryCycles()->count())->toBe(2)
        ->and(BatteryCycle::query()->open()->count())->toBe(1)
        ->and(MaintenanceEntry::query()->where('maintenance_task_id', $task->id)->count())->toBe(1)
        ->and($task->refresh()->last_completed_at)->not->toBeNull()
        ->and($task->is_active)->toBeTrue();

    // The 100% reading landed on the fresh (open) cycle.
    $current = $item->refresh()->currentBatteryCycle;
    expect($current->readings()->count())->toBe(1)
        ->and($current->latestReading->percent)->toBe(100);

    // The swap is logged as a maintenance completion, flagged auto.
    $event = Activity::query()->where('event', 'maintenance_completed')->latest('id')->first();
    expect($event->properties['auto'])->toBeTrue();
});

it('does not swap when the rise is too small', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 60, now()->subDay());
    $this->service->recordReading($item, 95, now()); // ≥90 but only +35

    expect($item->batteryCycles()->count())->toBe(1)
        ->and(MaintenanceEntry::query()->count())->toBe(0);
});

it('completes the task and opens a fresh cycle on an explicit change', function () {
    $item = Item::factory()->create();
    $this->service->recordReading($item, 50, now()->subMonth());

    $this->service->changeBattery($item, now(), notes: 'fresh pair');

    $task = forecastTask($item);

    expect($item->batteryCycles()->count())->toBe(2)
        ->and(BatteryCycle::query()->open()->count())->toBe(1)
        ->and($item->refresh()->currentBatteryCycle->notes)->toBe('fresh pair')
        ->and(MaintenanceEntry::query()->where('maintenance_task_id', $task->id)->count())->toBe(1)
        ->and($task->refresh()->is_active)->toBeTrue();
});

it('defers the regression to a queued job rather than running it inline', function () {
    Bus::fake([RefreshBatteryForecast::class]);
    $item = Item::factory()->create();

    $this->service->recordReading($item, 100, now()->subDays(20));
    $this->service->recordReading($item, 60, now());

    Bus::assertDispatched(RefreshBatteryForecast::class, fn (RefreshBatteryForecast $job): bool => $job->itemId === $item->id);

    // The reading is recorded synchronously, but the reminder isn't computed
    // until the job runs (faked here, so it never does).
    expect($item->batteryCycles()->count())->toBe(1)
        ->and(forecastTask($item)->next_due_at)->toBeNull();
});

it('caches the projection snapshot on the open cycle when the job runs', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 100, now()->subDays(20));
    $this->service->recordReading($item, 80, now()->subDays(10));
    $this->service->recordReading($item, 60, now());

    // Sync queue ran the job inline → the snapshot is on the open cycle.
    $forecast = $item->currentBatteryCycle->forecast;
    expect($forecast)->not->toBeNull()
        ->and($forecast['predicted_low_at'])->toBe(today()->addDays(20)->toDateString())
        ->and($forecast['sample_count'])->toBe(3);
});

it('projects the predicted-low date onto the reminder as readings come in', function () {
    $item = Item::factory()->create();

    // 100 → 80 → 60 over 20 days = -2%/day; from 60% low(20) is 20 days out.
    $this->service->recordReading($item, 100, now()->subDays(20));
    $this->service->recordReading($item, 80, now()->subDays(10));
    $this->service->recordReading($item, 60, now());

    $task = forecastTask($item);

    expect($task->next_due_at)->not->toBeNull()
        ->and($task->next_due_at->toDateString())->toBe(today()->addDays(20)->toDateString());
});

it('clamps a future reading timestamp to now', function () {
    $item = Item::factory()->create();

    $reading = $this->service->recordReading($item, 80, now()->addDays(5));

    expect($reading->recorded_at->isFuture())->toBeFalse()
        ->and($reading->recorded_at->lessThanOrEqualTo(now()->addSecond()))->toBeTrue();
});

it('does nothing when the forecast job runs for a deleted item', function () {
    $item = Item::factory()->create();

    expect(fn () => (new RefreshBatteryForecast($item->id + 999))->handle($this->service))
        ->not->toThrow(Exception::class);
});

it('clears the reminder date when the battery is not draining', function () {
    $item = Item::factory()->create();

    $this->service->recordReading($item, 50, now()->subDays(10));
    $this->service->recordReading($item, 50, now());

    expect(forecastTask($item)->next_due_at)->toBeNull();
});
