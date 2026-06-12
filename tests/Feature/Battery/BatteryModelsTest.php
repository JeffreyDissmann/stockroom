<?php

declare(strict_types=1);

use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes the open cycle as the current battery and lists cycles newest-install first', function () {
    // Type is a fixed property of the item, not the cycle.
    $item = Item::factory()->create(['battery_type' => 'CR2032']);

    $old = BatteryCycle::factory()->for($item)->closed(life: 90, removedDaysAgo: 5)->create();
    $current = BatteryCycle::factory()->for($item)->create(['installed_at' => now()->subDays(3)]);

    expect($item->currentBatteryCycle->is($current))->toBeTrue()
        ->and($item->battery_type)->toBe('CR2032')
        ->and($old->isOpen())->toBeFalse()
        ->and($current->isOpen())->toBeTrue();

    // batteryCycles is newest-install first.
    expect($item->batteryCycles->pluck('id')->all())->toBe([$current->id, $old->id]);

    // open() scope returns only the current cycle.
    expect(BatteryCycle::query()->open()->pluck('id')->all())->toBe([$current->id]);
});

it('returns null current cycle when the last battery was removed without replacement', function () {
    $item = Item::factory()->create();
    BatteryCycle::factory()->for($item)->closed()->create();

    expect($item->refresh()->currentBatteryCycle)->toBeNull();
});

it('orders readings oldest-first and exposes the latest', function () {
    $cycle = BatteryCycle::factory()->create();
    BatteryReading::factory()->forCycle($cycle)->create(['percent' => 60, 'recorded_at' => now()]);
    BatteryReading::factory()->forCycle($cycle)->create(['percent' => 90, 'recorded_at' => now()->subDays(4)]);
    BatteryReading::factory()->forCycle($cycle)->create(['percent' => 75, 'recorded_at' => now()->subDays(2)]);

    expect($cycle->readings->pluck('percent')->all())->toBe([90, 75, 60])
        ->and($cycle->latestReading->percent)->toBe(60);
});

it('exposes the latest battery reading on the item across cycles', function () {
    $item = Item::factory()->create();

    $old = BatteryCycle::factory()->for($item)->closed(life: 60, removedDaysAgo: 10)->create();
    BatteryReading::factory()->forCycle($old)->create(['percent' => 20, 'recorded_at' => now()->subDays(12)]);

    $current = BatteryCycle::factory()->for($item)->create(['installed_at' => now()->subDays(5)]);
    BatteryReading::factory()->forCycle($current)->create(['percent' => 95, 'recorded_at' => now()->subDays(5)]);
    BatteryReading::factory()->forCycle($current)->create(['percent' => 88, 'recorded_at' => now()]);

    expect($item->refresh()->latestBatteryReading->percent)->toBe(88);
});

it('cascades readings when an item is deleted', function () {
    $item = Item::factory()->create();
    $cycle = BatteryCycle::factory()->for($item)->create();
    BatteryReading::factory()->forCycle($cycle)->create();

    $item->delete();

    expect(BatteryCycle::count())->toBe(0)
        ->and(BatteryReading::count())->toBe(0);
});
