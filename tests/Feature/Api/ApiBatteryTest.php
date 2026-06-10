<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\User;
use App\Services\Battery\BatteryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiBatteryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'stockroom.battery.low_threshold' => 20,
            'stockroom.battery.change_detection.min_percent' => 90,
            'stockroom.battery.change_detection.min_jump' => 50,
        ]);
    }

    private function writer(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write']);

        return $user;
    }

    public function test_recording_a_reading_makes_the_item_battery_tracked(): void
    {
        $this->writer();
        $item = Item::factory()->create();

        $this->postJson("/api/v1/items/{$item->id}/battery-readings", ['percent' => 80])
            ->assertCreated()
            ->assertJsonPath('data.tracked', true)
            ->assertJsonPath('data.current_percent', 80);

        $this->assertSame(1, $item->batteryCycles()->count());
    }

    public function test_a_low_to_full_jump_is_recorded_as_a_swap(): void
    {
        $this->writer();
        $item = Item::factory()->create();

        $this->postJson("/api/v1/items/{$item->id}/battery-readings", [
            'percent' => 8,
            'recorded_at' => now()->subDay()->toIso8601String(),
        ])->assertCreated();

        $this->postJson("/api/v1/items/{$item->id}/battery-readings", ['percent' => 100])
            ->assertCreated()
            ->assertJsonPath('data.current_percent', 100);

        $this->assertSame(2, $item->batteryCycles()->count());
    }

    public function test_show_returns_the_projection_after_a_decline(): void
    {
        $this->writer();
        $item = Item::factory()->create();
        $service = app(BatteryService::class);

        // 100 → 80 → 60 over 20 days = -2%/day; low(20) is 20 days past today.
        $service->recordReading($item, 100, now()->subDays(20));
        $service->recordReading($item, 80, now()->subDays(10));
        $service->recordReading($item, 60, now());

        $this->getJson("/api/v1/items/{$item->id}/battery")
            ->assertOk()
            ->assertJsonPath('data.current_percent', 60)
            ->assertJsonPath('data.projection.predicted_low_at', today()->addDays(20)->toDateString())
            ->assertJsonPath('data.reminder.next_due_at', today()->addDays(20)->toDateString());
    }

    public function test_an_explicit_change_opens_a_fresh_cycle(): void
    {
        $this->writer();
        $item = Item::factory()->create();
        app(BatteryService::class)->recordReading($item, 40, now()->subMonth());

        $this->postJson("/api/v1/items/{$item->id}/battery-changes", ['notes' => 'new pair'])
            ->assertCreated()
            ->assertJsonPath('data.tracked', true);

        $this->assertSame(2, $item->batteryCycles()->count());
        $this->assertSame('new pair', $item->refresh()->currentBatteryCycle->notes);
    }

    public function test_percent_is_validated(): void
    {
        $this->writer();
        $item = Item::factory()->create();

        $this->postJson("/api/v1/items/{$item->id}/battery-readings", ['percent' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('percent');
    }

    public function test_reading_requires_write_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);
        $item = Item::factory()->create();

        $this->postJson("/api/v1/items/{$item->id}/battery-readings", ['percent' => 80])
            ->assertForbidden();
    }

    public function test_battery_endpoints_require_authentication(): void
    {
        $item = Item::factory()->create();

        $this->getJson("/api/v1/items/{$item->id}/battery")->assertUnauthorized();
        $this->postJson("/api/v1/items/{$item->id}/battery-readings", ['percent' => 80])->assertUnauthorized();
    }
}
