<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Services\Battery\BatteryForecast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item's battery state for Home Assistant: current level and type, the live
 * depletion projection, and the "Replace battery" reminder. The locale-neutral
 * API twin of the web battery panel (which gets the same data via Inertia).
 *
 * The forecast is resolved here rather than passed in because it is derived
 * state of the item's battery, like ItemResource computing its location_path;
 * this resource only ever serialises a single item, so the cost is bounded.
 *
 * @mixin Item
 */
class BatteryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Fresh queries (not cached relations): this resource is returned right
        // after a reading/swap mutates which cycle is current.
        $cycle = $this->currentBatteryCycle()->first();
        $latest = $cycle?->latestReading()->first();
        $projection = $cycle !== null ? app(BatteryForecast::class)->project($cycle) : null;
        $reminder = $this->maintenanceTasks()
            ->where('schedule_type', MaintenanceScheduleType::Forecast)
            ->first();

        return [
            'tracked' => $cycle !== null,
            'battery_type' => $this->battery_type,
            'current_percent' => $latest?->percent,
            'last_reading_at' => $latest?->recorded_at?->toIso8601String(),
            'is_low' => $latest !== null ? $latest->percent <= $this->lowThreshold() : null,
            'installed_at' => $cycle?->installed_at?->toIso8601String(),
            'projection' => $projection !== null ? [
                'rate_per_day' => round($projection->ratePerDay, 4),
                'predicted_low_at' => $projection->predictedLowAt->toDateString(),
                'predicted_empty_at' => $projection->predictedEmptyAt->toDateString(),
                'confidence' => round($projection->rSquared, 4),
                'sample_count' => $projection->sampleCount,
            ] : null,
            'reminder' => $reminder !== null ? [
                'next_due_at' => $reminder->next_due_at?->toDateString(),
                'is_overdue' => $reminder->isOverdue(),
                'reminder_lead_days' => $reminder->reminder_lead_days,
            ] : null,
        ];
    }

    private function lowThreshold(): int
    {
        return (int) config('stockroom.battery.low_threshold');
    }
}
