<?php

declare(strict_types=1);

namespace App\Services\Battery;

use App\Models\Setting;

/**
 * The household-wide battery percentage at or below which a battery counts as
 * low — the level the depletion forecast projects towards, and therefore what
 * drives every "Replace battery" reminder's due date.
 *
 * Admins set it on the household preferences page; `stockroom.battery.low_threshold`
 * (env `BATTERY_LOW_THRESHOLD`) remains the default for households that never
 * touch it. Read through here rather than the config directly, so the stored
 * preference can't be honoured in one place and ignored in another.
 */
class BatteryThreshold
{
    public const SETTING_KEY = 'battery_low_threshold';

    /** Bounds offered by the UI and enforced by validation. */
    public const MIN = 1;

    public const MAX = 50;

    /**
     * The configured low-battery percentage.
     */
    public static function lowPercent(): int
    {
        $stored = Setting::int(self::SETTING_KEY);

        if ($stored !== null && $stored >= self::MIN && $stored <= self::MAX) {
            return $stored;
        }

        return (int) config('stockroom.battery.low_threshold');
    }
}
