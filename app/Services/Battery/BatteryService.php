<?php

declare(strict_types=1);

namespace App\Services\Battery;

use App\Enums\MaintenanceScheduleType;
use App\Jobs\RefreshBatteryForecast;
use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Models\Setting;
use App\Models\Tag;
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

        // The regression runs off the request thread — see RefreshBatteryForecast.
        RefreshBatteryForecast::dispatch($item->id);
        $this->ensureBatteryTag($item);

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

        // The regression runs off the request thread — see RefreshBatteryForecast.
        RefreshBatteryForecast::dispatch($item->id);
        $this->ensureBatteryTag($item);

        return $cycle;
    }

    /**
     * Keep the auto-managed "Battery" tag on a battery-tracked item. Additive
     * (never disturbs other tags) and self-healing — if the tag was removed it
     * is re-added on the next reading. The item is only re-indexed when the tag
     * was actually attached, so steady-state readings stay cheap. The tag is
     * never removed here: a device stays "battery-powered" between batteries.
     */
    private function ensureBatteryTag(Item $item): void
    {
        $attached = $item->tags()->syncWithoutDetaching([$this->batteryTag()->id]);

        if (! empty($attached['attached'])) {
            $item->searchable();
        }
    }

    /**
     * The tag to auto-assign. Honours a household-selected battery tag if set,
     * else creates the default "Battery" tag on demand and records it as
     * selected — which then protects it from deletion in the Tags UI. Mirrors
     * HomeAssistantLinker.
     */
    private function batteryTag(): Tag
    {
        $selectedId = Setting::int('battery_tag_id');

        if ($selectedId !== null && ($selected = Tag::query()->find($selectedId)) !== null) {
            return $selected;
        }

        $tag = Tag::firstOrCreate(['name' => 'Battery'], ['color' => '#84cc16']);

        Setting::set('battery_tag_id', $tag->id);

        return $tag;
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
     * Re-run the depletion forecast for the current battery: project its
     * predicted-low date onto the reminder and cache the projection snapshot
     * on the open cycle (so the API and panel render without recomputing). The
     * reminder date is cleared when there is no usable prediction (too little
     * data, not draining, or the fit is below the configured confidence floor).
     *
     * This is the heavy regression step; it runs in RefreshBatteryForecast off
     * the request path, not inline with a reading.
     */
    public function refreshForecast(Item $item): void
    {
        $task = $this->ensureForecastTask($item);
        $cycle = $item->currentBatteryCycle()->first();

        $projection = $cycle !== null ? $this->forecast->project($cycle) : null;

        // Snapshot the projection for display even when its confidence is too
        // low to drive the reminder — the UI can label it accordingly.
        if ($cycle !== null) {
            $cycle->forecast = $projection !== null ? [
                'rate_per_day' => round($projection->ratePerDay, 4),
                'predicted_low_at' => $projection->predictedLowAt->toDateString(),
                'predicted_empty_at' => $projection->predictedEmptyAt->toDateString(),
                'confidence' => round($projection->rSquared, 4),
                'sample_count' => $projection->sampleCount,
            ] : null;
            $cycle->save();
        }

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
