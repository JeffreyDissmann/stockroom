<?php

declare(strict_types=1);

namespace App\Services\Battery;

use App\Models\BatteryCycle;
use App\Models\BatteryReading;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use MathPHP\Statistics\Regression\Linear;

/**
 * Extrapolates when a battery will need replacing from its level history.
 *
 * The fit pools the current battery with the last few completed cycles, each
 * re-based to "days since that battery was installed", into ONE age-aligned
 * least-squares line (MathPHP). This is self-weighting: a fresh battery's
 * slope leans on how previous batteries behaved, and as the current cycle's
 * own readings accumulate they come to dominate the pooled fit. The crossing
 * dates are then projected forward from the current battery's LATEST actual
 * reading, so the prediction reflects exactly where this battery is now.
 *
 * Returns null when the pooled samples can't support a fit (fewer than three,
 * no time spread), the battery isn't draining, or the current cycle has no
 * reading to anchor the projection.
 */
class BatteryForecast
{
    private const SECONDS_PER_DAY = 86400;

    public function project(BatteryCycle $cycle): ?BatteryProjection
    {
        $points = $this->pooledPoints($this->cohort($cycle));

        // A least-squares line needs positive degrees of freedom (n - 2 > 0),
        // so at least three samples, spread across at least two instants in
        // (battery-age) time. History pooling reaches this quickly.
        if ($points->count() < 3 || $points->pluck(0)->unique()->count() < 2) {
            return null;
        }

        $regression = new Linear($points->all());
        $ratePerDay = $regression->getParameters()['m'];

        // Not discharging (flat or charging): nothing to extrapolate toward.
        if ($ratePerDay >= -1e-6) {
            return null;
        }

        // Anchor the projection on the current battery's latest reading.
        $latest = $cycle->readings->last();

        if ($latest === null) {
            return null;
        }

        $installTimestamp = $cycle->installed_at->getTimestamp();
        $latestX = ($latest->recorded_at->getTimestamp() - $installTimestamp) / self::SECONDS_PER_DAY;
        $latestPercent = (float) $latest->percent;

        $daysToLow = ($latestPercent - $this->lowThreshold()) / (-$ratePerDay);
        $daysToEmpty = $latestPercent / (-$ratePerDay);

        return new BatteryProjection(
            ratePerDay: $ratePerDay,
            predictedLowAt: $this->dateFromOffset($installTimestamp, $latestX + $daysToLow),
            predictedEmptyAt: $this->dateFromOffset($installTimestamp, $latestX + $daysToEmpty),
            sampleCount: $points->count(),
            rSquared: $regression->r2(),
        );
    }

    /**
     * The current cycle plus the most-recent completed cycles that feed the
     * pooled fit, newest first.
     *
     * @return Collection<int, BatteryCycle>
     */
    private function cohort(BatteryCycle $cycle): Collection
    {
        $cycle->loadMissing('readings');

        $history = $cycle->item->batteryCycles()
            ->whereKeyNot($cycle->getKey())
            ->whereNotNull('removed_at')
            ->orderByDesc('installed_at')
            ->with('readings')
            ->take($this->historyCycles())
            ->get();

        return $history->prepend($cycle);
    }

    /**
     * Every cohort reading as an [ageInDays, percent] point, each cycle's
     * readings re-based to days since that cycle was installed.
     *
     * @param  Collection<int, BatteryCycle>  $cohort
     * @return SupportCollection<int, array{0:float,1:float}>
     */
    private function pooledPoints(Collection $cohort): SupportCollection
    {
        return $cohort->flatMap(function (BatteryCycle $cycle): SupportCollection {
            $installTimestamp = $cycle->installed_at->getTimestamp();

            return $cycle->readings->map(fn (BatteryReading $reading): array => [
                ($reading->recorded_at->getTimestamp() - $installTimestamp) / self::SECONDS_PER_DAY,
                (float) $reading->percent,
            ]);
        });
    }

    private function lowThreshold(): int
    {
        return (int) config('stockroom.battery.low_threshold');
    }

    private function historyCycles(): int
    {
        return (int) config('stockroom.battery.forecast.history_cycles');
    }

    private function dateFromOffset(int $refTimestamp, float $days): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp(
            $refTimestamp + (int) round($days * self::SECONDS_PER_DAY),
        );
    }
}
