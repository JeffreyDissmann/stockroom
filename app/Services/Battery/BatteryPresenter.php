<?php

declare(strict_types=1);

namespace App\Services\Battery;

use App\Enums\MaintenanceScheduleType;
use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;

/**
 * Serialises an item's battery state for the item page's Inertia props: the
 * summary the panel shows, and the per-cycle reading series the depletion
 * chart draws. The web twin of the API's BatteryResource (Resource = API,
 * Presenter = Inertia, mirroring MaintenancePresenter).
 */
class BatteryPresenter
{
    /**
     * Current level, type, the depletion projection and the reminder — for the
     * panel header. The projection is the cached snapshot on the open cycle
     * (written by RefreshBatteryForecast), so this never re-runs the regression.
     *
     * @return array<string, mixed>
     */
    public function summary(Item $item): array
    {
        $cycle = $item->currentBatteryCycle()->first();
        $latest = $cycle?->latestReading()->first();
        $reminder = $item->maintenanceTasks()
            ->where('schedule_type', MaintenanceScheduleType::Forecast)
            ->first();

        return [
            'tracked' => $cycle !== null,
            'battery_type' => $item->battery_type,
            'current_percent' => $latest?->percent,
            'last_reading_at' => $latest?->recorded_at?->toIso8601String(),
            'is_low' => $latest !== null ? $latest->percent <= $this->lowThreshold() : null,
            'installed_at' => $cycle?->installed_at?->toIso8601String(),
            'projection' => $cycle?->forecast,
            'reminder' => $reminder !== null ? [
                'next_due_at' => $reminder->next_due_at?->toDateString(),
                'is_overdue' => $reminder->isOverdue(),
            ] : null,
        ];
    }

    /**
     * Every battery cycle newest-install first, each with its reading series,
     * for the depletion chart — one line per physical battery (current + past).
     *
     * @return list<array<string, mixed>>
     */
    public function cycles(Item $item): array
    {
        return $item->batteryCycles()
            ->with('readings')
            ->get()
            ->map(fn (BatteryCycle $cycle): array => [
                'id' => $cycle->id,
                'installed_at' => $cycle->installed_at->toIso8601String(),
                'removed_at' => $cycle->removed_at?->toIso8601String(),
                'is_current' => $cycle->isOpen(),
                'readings' => $cycle->readings
                    ->map(fn (BatteryReading $reading): array => [
                        'recorded_at' => $reading->recorded_at->toIso8601String(),
                        'percent' => $reading->percent,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function lowThreshold(): int
    {
        return (int) config('stockroom.battery.low_threshold');
    }
}
