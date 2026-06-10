<?php

declare(strict_types=1);

namespace App\Enums;

enum MaintenanceScheduleType: string
{
    /**
     * Next due date drifts with reality: last completion + interval.
     * The right fit for batteries, filters, descaling.
     */
    case Interval = 'interval';

    /**
     * Fixed calendar cadence (RFC 5545 RRULE) regardless of when the task
     * was actually completed, e.g. "every first Sunday in March".
     */
    case Calendar = 'calendar';

    /**
     * A single due date; the task archives itself when completed.
     */
    case OneOff = 'one_off';

    /**
     * Due date is owned by an external forecast, not a stored rule — the
     * battery depletion projection writes next_due_at through
     * MaintenanceSchedule::applyForecast(). Completing it (a battery change)
     * clears the date until the fresh battery's slope is learned. System
     * managed: created when an item becomes battery-tracked, never offered
     * in the schedule picker.
     */
    case Forecast = 'forecast';

    public function label(): string
    {
        return __('enums.maintenance_schedule_type.'.$this->value);
    }

    /**
     * Skipping an occurrence only makes sense on a fixed cadence — interval
     * tasks simply stay due until done, and one-offs have nothing to skip to.
     */
    public function isSkippable(): bool
    {
        return $this === self::Calendar;
    }
}
