<?php

declare(strict_types=1);

use App\Models\BatteryCycle;
use App\Models\Item;
use App\Services\Battery\BatteryRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->recorder = app(BatteryRecorder::class);
});

it('opens a first cycle when the item has no battery tracked yet', function () {
    $item = Item::factory()->create();

    $reading = $this->recorder->recordReading($item, 80, now());

    expect($item->batteryCycles()->count())->toBe(1)
        ->and($reading->percent)->toBe(80)
        ->and($reading->battery_cycle_id)->toBe($item->currentBatteryCycle->id)
        ->and($reading->item_id)->toBe($item->id);
});

it('appends to the same open cycle on a normal decline', function () {
    $item = Item::factory()->create();

    $this->recorder->recordReading($item, 80, now()->subDays(2));
    $this->recorder->recordReading($item, 60, now());

    expect($item->batteryCycles()->count())->toBe(1)
        ->and($item->currentBatteryCycle->readings()->count())->toBe(2);
});

it('auto-detects a battery change on a low-to-full jump and splits cycles', function () {
    $item = Item::factory()->create();

    $this->recorder->recordReading($item, 8, now()->subDays(1));
    $this->recorder->recordReading($item, 100, now());

    expect($item->batteryCycles()->count())->toBe(2)
        ->and(BatteryCycle::query()->open()->count())->toBe(1);

    $current = $item->refresh()->currentBatteryCycle;

    // The full reading landed on the fresh cycle, not the closed one.
    expect($current->readings()->count())->toBe(1)
        ->and($current->latestReading->percent)->toBe(100)
        ->and($current->isOpen())->toBeTrue();
});

it('does not split when the rise is too small to be a swap', function () {
    $item = Item::factory()->create();

    $this->recorder->recordReading($item, 60, now()->subDays(1));
    $this->recorder->recordReading($item, 95, now()); // up to >=90 but only +35

    expect($item->batteryCycles()->count())->toBe(1)
        ->and($item->currentBatteryCycle->readings()->count())->toBe(2);
});

it('does not split on the very first reading even if it is full', function () {
    $item = Item::factory()->create();

    $this->recorder->recordReading($item, 100, now());

    expect($item->batteryCycles()->count())->toBe(1);
});

it('closes the open cycle and opens a new one on an explicit change', function () {
    $item = Item::factory()->create();
    $old = BatteryCycle::factory()->for($item)->create(['installed_at' => now()->subMonths(3)]);

    $new = $this->recorder->changeBattery($item, now(), notes: 'fresh pair');

    expect($old->refresh()->isOpen())->toBeFalse()
        ->and($new->isOpen())->toBeTrue()
        ->and($new->notes)->toBe('fresh pair')
        ->and($item->batteryCycles()->count())->toBe(2);
});

it('compresses a flat run into start and trailing rows, sliding the trailing timestamp', function () {
    $item = Item::factory()->create();

    $t0 = now()->subHours(3);
    $t3 = now();

    $this->recorder->recordReading($item, 100, $t0);
    $this->recorder->recordReading($item, 100, now()->subHours(2)); // first repeat → trailing row
    $this->recorder->recordReading($item, 100, now()->subHour());   // slides trailing
    $this->recorder->recordReading($item, 100, $t3);                // slides trailing

    $readings = $item->currentBatteryCycle->readings; // oldest first

    expect($readings)->toHaveCount(2)
        ->and($readings->pluck('percent')->all())->toBe([100, 100])
        ->and($readings->first()->recorded_at->toDateTimeString())->toBe($t0->toDateTimeString())
        ->and($readings->last()->recorded_at->toDateTimeString())->toBe($t3->toDateTimeString());
});

it('starts a fresh row when the value changes after a flat run', function () {
    $item = Item::factory()->create();

    $this->recorder->recordReading($item, 100, now()->subHours(3));
    $this->recorder->recordReading($item, 100, now()->subHours(2)); // trailing
    $this->recorder->recordReading($item, 90, now()->subHour());    // change → new row
    $this->recorder->recordReading($item, 90, now());               // first repeat → trailing
    $this->recorder->recordReading($item, 90, now()->addMinute());  // slides, no new row

    $readings = $item->currentBatteryCycle->readings;

    expect($readings->pluck('percent')->all())->toBe([100, 100, 90, 90]);
});

it('clamps percent into the 0-100 range', function () {
    $item = Item::factory()->create();

    expect($this->recorder->recordReading($item, 150, now())->percent)->toBe(100)
        ->and($this->recorder->recordReading($item, -5, now())->percent)->toBe(0);
});
