<?php

declare(strict_types=1);

use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Setting;
use App\Services\Battery\BatteryForecast;
use App\Services\Battery\BatteryThreshold;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to the configured default when no preference is stored', function () {
    config(['stockroom.battery.low_threshold' => 20]);

    expect(BatteryThreshold::lowPercent())->toBe(20);
});

it('prefers the stored household preference over the config default', function () {
    config(['stockroom.battery.low_threshold' => 20]);
    Setting::set(BatteryThreshold::SETTING_KEY, 5);

    expect(BatteryThreshold::lowPercent())->toBe(5);
});

it('ignores a stored value outside the supported range', function (int $stored) {
    config(['stockroom.battery.low_threshold' => 20]);
    Setting::set(BatteryThreshold::SETTING_KEY, $stored);

    expect(BatteryThreshold::lowPercent())->toBe(20);
})->with([
    'zero' => 0,
    'negative' => -5,
    'above max' => BatteryThreshold::MAX + 1,
]);

it('accepts the range boundaries', function () {
    Setting::set(BatteryThreshold::SETTING_KEY, BatteryThreshold::MIN);
    expect(BatteryThreshold::lowPercent())->toBe(BatteryThreshold::MIN);

    Setting::set(BatteryThreshold::SETTING_KEY, BatteryThreshold::MAX);
    expect(BatteryThreshold::lowPercent())->toBe(BatteryThreshold::MAX);
});

it('drives the forecast, so the predicted low date moves with it', function () {
    config(['stockroom.battery.low_threshold' => 20]);

    // Draining a clean 1% per day from 100%: it hits 20% on day 80 and 5% on
    // day 95. Lowering the threshold should push the prediction later — that
    // shift is the whole point of the setting.
    $install = CarbonImmutable::parse('2026-01-01');
    $cycle = BatteryCycle::factory()->create([
        'installed_at' => $install,
        'removed_at' => null,
    ]);

    foreach ([[0, 100], [10, 90], [20, 80]] as [$days, $percent]) {
        BatteryReading::factory()->forCycle($cycle)->create([
            'percent' => $percent,
            'recorded_at' => $install->addDays($days),
        ]);
    }

    $forecast = app(BatteryForecast::class);

    $atTwenty = $forecast->project($cycle->refresh());
    Setting::set(BatteryThreshold::SETTING_KEY, 5);
    $atFive = $forecast->project($cycle->refresh());

    expect($atTwenty)->not->toBeNull()
        ->and($atFive)->not->toBeNull()
        ->and($atFive->predictedLowAt->greaterThan($atTwenty->predictedLowAt))->toBeTrue();
});
