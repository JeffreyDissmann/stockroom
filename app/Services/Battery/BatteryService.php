<?php

declare(strict_types=1);

namespace App\Services\Battery;

use App\Enums\MaintenanceScheduleType;
use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Services\Maintenance\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Coordinates battery tracking: the policy layer over the mechanical
 * BatteryRecorder, the BatteryForecast, and the maintenance schedule.
 *
 * Every battery-tracked item carries one system-managed "Replace battery"
 * forecast task. A battery change — auto-detected from a low→full reading
 * jump or done explicitly — always flows through changeBattery(), which both
 * swaps the cycle and records the change AS a completion of that task. So the
 * reminder, the maintenance history and the activity feed all stay in sync
 * with reality through a single owner.
 */
class BatteryService
{
    public function __construct(
        private readonly BatteryRecorder $recorder,
        private readonly BatteryForecast $forecast,
        private readonly MaintenanceSchedule $schedule,
    ) {}

    /**
     * Record a level sample. Makes the item battery-tracked on the first
     * reading, auto-detects a battery swap from a low→full jump, appends the
     * sample, and refreshes the depletion forecast.
     */
    public function recordReading(Item $item, int $percent, CarbonInterface|string|null $at = null): BatteryReading
    {
        $percent = max(0, min(100, $percent));
        $at = $at ? CarbonImmutable::parse($at) : CarbonImmutable::now();

        $this->ensureForecastTask($item);

        $last = $item->currentBatteryCycle()->first()?->latestReading()->first();

        if ($last !== null && $this->looksLikeChange($last->percent, $percent)) {
            $this->changeBattery($item, $at, auto: true);
        }

        $reading = $this->recorder->recordReading($item, $percent, $at);

        $this->refreshForecast($item);

        return $reading;
    }

    /**
     * Record a battery change: swap the cycle and complete the "Replace
     * battery" task in one transaction, then re-forecast the fresh battery.
     * The single path every change funnels through.
     */
    public function changeBattery(Item $item, CarbonInterface|string|null $at = null, ?string $notes = null, ?int $performedBy = null, bool $auto = false): BatteryCycle
    {
        $at = $at ? CarbonImmutable::parse($at) : CarbonImmutable::now();
        $task = $this->ensureForecastTask($item);

        $cycle = DB::transaction(function () use ($item, $task, $at, $notes, $performedBy, $auto): BatteryCycle {
            $cycle = $this->recorder->changeBattery($item, $at, $notes);

            $item->maintenanceEntries()->create([
                'maintenance_task_id' => $task->id,
                'performed_by' => $performedBy,
                'completed_at' => $at,
                'notes' => $notes,
                'cost' => null,
            ]);

            $this->schedule->applyCompletion($task, $at);
            $task->save();

            $item->logMaintenanceActivity('maintenance_completed', [
                'task_title' => $task->title,
                'auto' => $auto,
            ]);

            return $cycle;
        });

        $this->refreshForecast($item);

        return $cycle;
    }

    /**
     * The item's "Replace battery" task, created on first use. Carries no
     * stored rule (the forecast owns its due date); its reminder lead gives a
     * configurable heads-up window before the predicted-low date.
     */
    public function ensureForecastTask(Item $item): MaintenanceTask
    {
        return $item->maintenanceTasks()->firstOrCreate(
            ['schedule_type' => MaintenanceScheduleType::Forecast],
            [
                'title' => __('maintenance.battery.replace_title'),
                'reminder_lead_days' => (int) config('stockroom.battery.reminder_lead_days'),
                'is_active' => true,
                'next_due_at' => null,
            ],
        );
    }

    /**
     * Re-run the depletion forecast for the current battery and project its
     * predicted-low date onto the reminder. Clears the date when there is no
     * usable prediction (too little data, not draining, or the fit is below
     * the configured confidence floor).
     */
    public function refreshForecast(Item $item): void
    {
        $task = $this->ensureForecastTask($item);
        $cycle = $item->currentBatteryCycle()->first();

        $projection = $cycle !== null ? $this->forecast->project($cycle) : null;

        $predictedLowAt = $projection !== null && $projection->rSquared >= $this->minRSquared()
            ? $projection->predictedLowAt
            : null;

        $this->schedule->applyForecast($task, $predictedLowAt);
        $task->save();
    }

    /**
     * A new sample reads as a fresh battery when it jumps up to at least
     * min_percent and rises by at least min_jump over the last level.
     */
    private function looksLikeChange(int $lastPercent, int $percent): bool
    {
        $rule = config('stockroom.battery.change_detection');

        return $percent >= $rule['min_percent']
            && ($percent - $lastPercent) >= $rule['min_jump'];
    }

    private function minRSquared(): float
    {
        return (float) config('stockroom.battery.forecast.min_r_squared');
    }
}
