<?php

declare(strict_types=1);

namespace App\Services\Battery;

use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use App\Models\Item;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The mechanical writer of battery cycles and readings. Owns the
 * one-open-cycle-per-item invariant (no DB partial index — enforced here so
 * the model stays portable across pgsql/sqlite) and the auto-detection of a
 * battery swap from the reading stream Home Assistant pushes.
 *
 * Knows nothing about maintenance or the activity feed: a battery change is
 * surfaced as a "Replace battery" maintenance-task completion by the layer
 * above (see the battery↔maintenance coordinator), which calls changeBattery
 * to perform the physical swap.
 */
class BatteryRecorder
{
    /**
     * Append a level sample to the item's current battery. Opens a first
     * cycle if none exists yet, and auto-detects a swap when the level jumps
     * from low back up to full (config stockroom.battery.change_detection):
     * that closes the old cycle and opens a new one before recording, so the
     * reading lands on the fresh battery. Readings are never activity-logged.
     */
    public function recordReading(Item $item, int $percent, ?CarbonInterface $at = null): BatteryReading
    {
        $percent = max(0, min(100, $percent));
        $at = $at ? CarbonImmutable::parse($at) : CarbonImmutable::now();

        $cycle = $item->currentBatteryCycle()->first();

        if ($cycle === null) {
            $cycle = $this->openCycle($item, $at);
        } elseif ($this->looksLikeChange($cycle, $percent)) {
            $cycle = $this->changeBattery($item, $at);
        }

        return $this->appendCompressed($cycle, $item, $percent, $at);
    }

    /**
     * Append a sample with flat-run compression. Home Assistant re-reports the
     * same level every minute; storing each row would bloat the table and add
     * no shape to the curve. So a changed value starts a new row, the first
     * repeat adds a trailing row, and every further repeat just slides that
     * trailing row's timestamp forward — keeping exactly the two endpoints of
     * each flat run: when the level was reached, and when it was last seen.
     */
    private function appendCompressed(BatteryCycle $cycle, Item $item, int $percent, CarbonImmutable $at): BatteryReading
    {
        $recent = $cycle->readings()->reorder('recorded_at', 'desc')->take(2)->get();
        [$last, $secondLast] = $recent->pad(2, null);

        // Same value, and the last row is already this run's trailing row:
        // just slide it forward instead of inserting another.
        if ($last?->percent === $percent && $secondLast?->percent === $percent) {
            $last->update(['recorded_at' => $at]);

            return $last;
        }

        // A changed value (or the cycle's first reading) starts a new point;
        // the first repeat of the current value adds its trailing row.
        return $cycle->readings()->create([
            'item_id' => $item->id,
            'percent' => $percent,
            'recorded_at' => $at,
        ]);
    }

    /**
     * Perform the physical battery swap: close the open cycle (if any) and
     * open a fresh one, atomically. The feed entry, history and schedule
     * reset are the maintenance layer's job — it completes the "Replace
     * battery" task, which calls this.
     */
    public function changeBattery(Item $item, ?CarbonInterface $at = null, ?string $notes = null): BatteryCycle
    {
        $at = $at ? CarbonImmutable::parse($at) : CarbonImmutable::now();

        return DB::transaction(function () use ($item, $at, $notes): BatteryCycle {
            $item->currentBatteryCycle()->first()?->update(['removed_at' => $at]);

            return $this->openCycle($item, $at, $notes);
        });
    }

    /**
     * Whether a new sample on the open cycle reads as a fresh battery: a jump
     * up to at least min_percent that also rises by at least min_jump points
     * over the last recorded level.
     */
    private function looksLikeChange(BatteryCycle $cycle, int $percent): bool
    {
        $last = $cycle->latestReading()->first();

        if ($last === null) {
            return false;
        }

        $rule = config('stockroom.battery.change_detection');

        return $percent >= $rule['min_percent']
            && ($percent - $last->percent) >= $rule['min_jump'];
    }

    private function openCycle(Item $item, CarbonImmutable $at, ?string $notes = null): BatteryCycle
    {
        return $item->batteryCycles()->create([
            'installed_at' => $at,
            'removed_at' => null,
            'notes' => $notes,
        ]);
    }
}
