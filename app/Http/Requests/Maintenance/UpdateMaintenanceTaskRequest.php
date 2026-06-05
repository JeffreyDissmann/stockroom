<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenanceScheduleType;
use App\Models\MaintenanceTask;

/**
 * Same payload as store, with one relaxation: a task that is already a
 * calendar task may omit schedule_preset to keep its stored RRULE — that
 * is how "custom rule" tasks (rules the presets cannot express) survive a
 * title or lead-time edit without the dialog having to rebuild the rule.
 */
class UpdateMaintenanceTaskRequest extends StoreMaintenanceTaskRequest
{
    protected function schedulePresetPresenceRules(): array
    {
        $task = $this->route('maintenanceTask');
        $alreadyCalendar = $task instanceof MaintenanceTask
            && $task->schedule_type === MaintenanceScheduleType::Calendar;

        return $alreadyCalendar ? ['nullable'] : ['required_if:schedule_type,calendar', 'nullable'];
    }
}
