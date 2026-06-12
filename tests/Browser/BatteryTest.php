<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use App\Services\Battery\BatteryService;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the battery panel and chart with no JS errors once the item is tracked', function () {
    $item = Item::factory()->create(['name' => 'Smoke Detector', 'battery_type' => 'CR2032']);

    // A declining series so the panel shows a level, a prediction and a chart.
    $service = app(BatteryService::class);
    $service->recordReading($item, 100, now()->subDays(20));
    $service->recordReading($item, 80, now()->subDays(10));
    $service->recordReading($item, 60, now());

    $page = visit("/items/{$item->id}");

    $page->assertSee('Smoke Detector')
        ->assertPresent('@battery-section')
        ->assertPresent('@battery-change')
        ->assertSee('CR2032')
        ->assertNoJavaScriptErrors();
});

it('hides the battery panel for an item with no battery history', function () {
    $item = Item::factory()->create(['name' => 'Wooden Chair']);

    $page = visit("/items/{$item->id}");

    $page->assertSee('Wooden Chair')
        ->assertMissing('@battery-section')
        ->assertNoJavaScriptErrors();
});

it('shows the battery type picker on the item form', function () {
    $item = Item::factory()->create(['name' => 'Remote']);

    $page = visit("/items/{$item->id}/edit");

    $page->assertPresent('@item-battery-type')
        ->assertNoJavaScriptErrors();
});
