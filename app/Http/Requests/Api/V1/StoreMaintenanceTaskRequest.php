<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create a maintenance schedule on an item via the API (e.g. Home Assistant
 * setting up a reminder). Interval and one-off only — fixed-calendar (RRULE)
 * schedules come from the web preset builder and are out of scope here, same
 * as the assistant's create tool.
 */
class StoreMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Token + `write` ability (enforced by route middleware) is the
        // authorization; the single household has no per-user scoping.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'schedule_type' => ['required', Rule::enum(MaintenanceScheduleType::class)->only([
                MaintenanceScheduleType::Interval,
                MaintenanceScheduleType::OneOff,
            ])],
            'reminder_lead_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'interval_value' => ['required_if:schedule_type,interval', 'nullable', 'integer', 'min:1', 'max:999'],
            'interval_unit' => ['required_if:schedule_type,interval', 'nullable', new Enum(MaintenanceIntervalUnit::class)],

            'next_due_at' => ['required_if:schedule_type,one_off', 'nullable', 'date'],
        ];
    }

    /**
     * The validated payload as model-ready MaintenanceTask attributes. The
     * rule columns of the other schedule type are explicitly nulled so the
     * row never carries a stale rule. next_due_at for interval tasks is
     * derived by MaintenanceSchedule::recompute() in the controller.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $validated = $this->validated();
        $type = MaintenanceScheduleType::from($validated['schedule_type']);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'schedule_type' => $type,
            'reminder_lead_days' => $validated['reminder_lead_days'] ?? 7,
            'interval_value' => $type === MaintenanceScheduleType::Interval ? $validated['interval_value'] : null,
            'interval_unit' => $type === MaintenanceScheduleType::Interval ? $validated['interval_unit'] : null,
            'rrule' => null,
            'next_due_at' => $type === MaintenanceScheduleType::OneOff ? $validated['next_due_at'] : null,
        ];
    }
}
