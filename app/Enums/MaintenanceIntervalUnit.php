<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum MaintenanceIntervalUnit: string
{
    case Days = 'days';
    case Weeks = 'weeks';
    case Months = 'months';
    case Years = 'years';

    public function label(): string
    {
        return __('enums.maintenance_interval_unit.'.$this->value);
    }

    public function addTo(CarbonImmutable $date, int $value): CarbonImmutable
    {
        return match ($this) {
            self::Days => $date->addDays($value),
            self::Weeks => $date->addWeeks($value),
            // "1 month after Jan 31" should be Feb 28/29, not Mar 2/3.
            self::Months => $date->addMonthsNoOverflow($value),
            self::Years => $date->addYearsNoOverflow($value),
        };
    }
}
