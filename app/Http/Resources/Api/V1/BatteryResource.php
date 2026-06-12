<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item's battery state for Home Assistant: current level and type, the
 * depletion projection, and the "Replace battery" reminder. The locale-neutral
 * API twin of the web battery panel (which gets the same data via Inertia).
 *
 * The projection is read from the cached snapshot on the open cycle (written
 * by RefreshBatteryForecast after each reading), so serialising never re-runs
 * the regression — recording a level stays a cheap insert.
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
            'projection' => $cycle?->forecast,
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
