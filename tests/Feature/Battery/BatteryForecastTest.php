<?php

declare(strict_types=1);

use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;
use App\Services\Battery\BatteryForecast;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->forecast = app(BatteryForecast::class);
    config(['stockroom.battery.low_threshold' => 20]);
});

/**
 * Add a cycle to $item, readings re-based to $installAt + offsetDays.
 *
 * @param  array<int, array{0:int,1:int}>  $samples  [daysFromInstall, percent]
 */
function cycleFor(Item $item, string $installAt, ?string $removedAt, array $samples): BatteryCycle
{
    $install = CarbonImmutable::parse($installAt);

    $cycle = BatteryCycle::factory()->for($item)->create([
        'installed_at' => $install,
        'removed_at' => $removedAt !== null ? CarbonImmutable::parse($removedAt) : null,
    ]);

    foreach ($samples as [$days, $percent]) {
        BatteryReading::factory()->forCycle($cycle)->create([
            'percent' => $percent,
            'recorded_at' => $install->addDays($days),
        ]);
    }

    return $cycle->refresh();
}

/**
 * Build a cycle with readings at $base + offsetDays, in the given percents.
 *
 * @param  array<int, array{0:int,1:int}>  $samples  [daysFromBase, percent]
 */
function cycleWith(CarbonImmutable $base, array $samples): BatteryCycle
{
    $cycle = BatteryCycle::factory()->create();

    foreach ($samples as [$days, $percent]) {
        BatteryReading::factory()->forCycle($cycle)->create([
            'percent' => $percent,
            'recorded_at' => $base->addDays($days),
        ]);
    }

    return $cycle->refresh();
}

it('projects the low and empty crossing dates from a clean declining series', function () {
    $base = CarbonImmutable::parse('2026-01-01');
    // 100 → 80 → 60 over 20 days = -2%/day. From 60%: low(20) in 20d, empty in 30d.
    $cycle = cycleWith($base, [[0, 100], [10, 80], [20, 60]]);

    $projection = $this->forecast->project($cycle);

    expect($projection)->not->toBeNull()
        ->and(round($projection->ratePerDay, 4))->toBe(-2.0)
        ->and($projection->sampleCount)->toBe(3)
        ->and($projection->rSquared)->toBe(1.0) // a perfect line
        ->and($projection->predictedLowAt->toDateString())->toBe('2026-02-10')   // day 40
        ->and($projection->predictedEmptyAt->toDateString())->toBe('2026-02-20'); // day 50
});

it('moves the prediction with the configured low threshold', function () {
    $base = CarbonImmutable::parse('2026-01-01');
    $cycle = cycleWith($base, [[0, 100], [10, 80], [20, 60]]);

    config(['stockroom.battery.low_threshold' => 40]);

    // From 60% at -2%/day, low(40) is 10 days out → day 30.
    expect($this->forecast->project($cycle)->predictedLowAt->toDateString())->toBe('2026-01-31');
});

it('returns a past low date (overdue) when the battery is already below the threshold', function () {
    $base = CarbonImmutable::parse('2026-01-01');
    $cycle = cycleWith($base, [[0, 30], [10, 25], [20, 15]]); // latest 15 < 20

    $projection = $this->forecast->project($cycle);

    expect($projection)->not->toBeNull()
        ->and($projection->predictedLowAt->lt($cycle->latestReading->recorded_at))->toBeTrue();
});

it('returns null with fewer than three readings (degrees of freedom)', function (array $samples) {
    $cycle = cycleWith(CarbonImmutable::parse('2026-01-01'), $samples);

    expect($this->forecast->project($cycle))->toBeNull();
})->with([
    'one' => [[[0, 80]]],
    'two' => [[[0, 90], [10, 70]]],
]);

it('returns null when the battery is not draining', function (array $samples) {
    $cycle = cycleWith(CarbonImmutable::parse('2026-01-01'), $samples);

    expect($this->forecast->project($cycle))->toBeNull();
})->with([
    'flat' => [[[0, 50], [10, 50], [20, 50]]],
    'charging' => [[[0, 50], [10, 60], [20, 70]]],
]);

it('returns null when all samples share an instant', function () {
    $base = CarbonImmutable::parse('2026-01-01');
    $cycle = cycleWith($base, [[0, 80], [0, 60]]); // same recorded_at

    expect($this->forecast->project($cycle))->toBeNull();
});

it('forecasts a fresh battery from prior cycles before it has a trend of its own', function () {
    $item = Item::factory()->create();

    // A completed battery that drained 100 → 60 over 20 days.
    cycleFor($item, '2025-11-01', '2025-11-21', [[0, 100], [20, 60]]);

    // The current battery has a single reading — no trend on its own.
    $current = cycleFor($item, '2026-01-01', null, [[0, 90]]);

    $projection = $this->forecast->project($current);

    // Pooled age-aligned fit over (0,100),(20,60),(0,90) → -1.75%/day; from
    // 90% that is low(20) in 40 days. A forecast at all proves history fed it.
    expect($projection)->not->toBeNull()
        ->and(round($projection->ratePerDay, 4))->toBe(-1.75)
        ->and($projection->sampleCount)->toBe(3)
        ->and($projection->predictedLowAt->toDateString())->toBe('2026-02-10');
});

it('pools only the configured number of most-recent completed cycles', function () {
    $item = Item::factory()->create();

    cycleFor($item, '2025-06-01', '2025-06-21', [[0, 100], [20, 80]]); // ancient (2 readings)
    cycleFor($item, '2025-12-01', '2025-12-21', [[0, 100], [20, 60]]); // recent (2 readings)
    $current = cycleFor($item, '2026-01-01', null, [[0, 90]]);          // current (1 reading)

    config(['stockroom.battery.forecast.history_cycles' => 1]);

    // Cap of 1 → current (1) + recent prior (2) only; the ancient cycle's 2
    // readings are excluded.
    expect($this->forecast->project($current)->sampleCount)->toBe(3);
});
