<?php

declare(strict_types=1);

namespace App\Services\Battery;

use Carbon\CarbonImmutable;

/**
 * The outcome of extrapolating a battery's discharge: the fitted rate and
 * the dates it is projected to hit the low threshold (when a replacement is
 * due) and 0% (flat-empty, for the depletion chart). Produced only when the
 * battery is actually draining off enough samples — see BatteryForecast.
 */
final readonly class BatteryProjection
{
    public function __construct(
        /** Fitted discharge rate in percent per day; always negative here. */
        public float $ratePerDay,
        /** Projected date the level reaches the low threshold (may be past → overdue). */
        public CarbonImmutable $predictedLowAt,
        /** Projected date the level reaches 0%. */
        public CarbonImmutable $predictedEmptyAt,
        /** How many readings the fit was based on, pooled across the cohort of cycles. */
        public int $sampleCount,
        /** Coefficient of determination (0–1): how well the line fits — i.e. how trustworthy the date is. */
        public float $rSquared,
    ) {}
}
