<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use App\Services\Battery\BatteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    config([
        'stockroom.battery.low_threshold' => 20,
        'stockroom.battery.reminder_lead_days' => 3,
    ]);
});

it('records a manual battery change from the item page', function () {
    $item = Item::factory()->create();
    app(BatteryService::class)->recordReading($item, 40, now()->subMonth());

    $this->actingAs($this->user)
        ->from(route('items.show', $item))
        ->post(route('items.battery-changes.store', $item), ['notes' => 'new pair'])
        ->assertRedirect(route('items.show', $item))
        ->assertSessionHasNoErrors();

    expect($item->batteryCycles()->count())->toBe(2)
        ->and($item->refresh()->currentBatteryCycle->notes)->toBe('new pair');
});

it('passes battery summary and cycles to the item page', function () {
    $item = Item::factory()->create(['battery_type' => 'CR2032']);
    $service = app(BatteryService::class);
    $service->recordReading($item, 100, now()->subDays(20));
    $service->recordReading($item, 80, now()->subDays(10));
    $service->recordReading($item, 60, now());

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertInertia(fn (Assert $page) => $page
            ->where('battery.summary.tracked', true)
            ->where('battery.summary.battery_type', 'CR2032')
            ->where('battery.summary.current_percent', 60)
            ->where('battery.summary.projection.predicted_low_at', today()->addDays(20)->toDateString())
            ->has('battery.cycles', 1)
            ->has('battery.cycles.0.readings', 3)
        );
});

it('seeds demo battery devices on a fresh install', function () {
    $this->seed();

    $detector = Item::where('name', 'Smoke detector')->sole();
    $sensor = Item::where('name', 'Door sensor')->sole();

    // The detector has a spent + a current battery (an auto-detected swap),
    // a future replacement date, and is not low.
    expect($detector->batteryCycles()->count())->toBe(2)
        ->and($detector->maintenanceTasks()->where('schedule_type', 'forecast')->whereNotNull('next_due_at')->exists())->toBeTrue()
        ->and($detector->latestBatteryReading->percent)->toBe(60);

    // The sensor is drained into the low band with an overdue reminder.
    $sensorTask = $sensor->maintenanceTasks()->where('schedule_type', 'forecast')->first();
    expect($sensor->batteryCycles()->count())->toBe(1)
        ->and($sensor->latestBatteryReading->percent)->toBe(14)
        ->and($sensorTask->isOverdue())->toBeTrue();
});

it('excludes the battery forecast task from the maintenance task list', function () {
    $item = Item::factory()->create();
    app(BatteryService::class)->recordReading($item, 50, now());

    $this->actingAs($this->user)
        ->get(route('items.show', $item))
        ->assertInertia(fn (Assert $page) => $page->where('maintenance.tasks', []));
});
