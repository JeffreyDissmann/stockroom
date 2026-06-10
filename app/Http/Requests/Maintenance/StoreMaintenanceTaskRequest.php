<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\MaintenanceTask;
use App\Services\Maintenance\SchedulePresets;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

/**
 * Validates the maintenance-task dialog payload. The schedule_type decides
 * which rule fields apply:
 *  - interval: interval_value + interval_unit
 *  - calendar: schedule_preset (the curated builder payload — the server
 *    converts it to an RRULE; clients never send raw rules)
 *  - one_off:  next_due_at
 */
class StoreMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Forecast tasks are system-managed (created when an item becomes
            // battery-tracked); users pick from the rule-based types only.
            'schedule_type' => ['required', (new Enum(MaintenanceScheduleType::class))->only([
                MaintenanceScheduleType::Interval,
                MaintenanceScheduleType::Calendar,
                MaintenanceScheduleType::OneOff,
            ])],
            'reminder_lead_days' => ['nullable', 'integer', 'min:0', 'max:365'],

            'interval_value' => ['required_if:schedule_type,interval', 'nullable', 'integer', 'min:1', 'max:999'],
            'interval_unit' => ['required_if:schedule_type,interval', 'nullable', new Enum(MaintenanceIntervalUnit::class)],

            'schedule_preset' => [...$this->schedulePresetPresenceRules(), 'array'],
            'schedule_preset.preset' => ['required_with:schedule_preset', 'in:every,yearly_on,nth_weekday'],
            'schedule_preset.interval' => ['required_if:schedule_preset.preset,every', 'nullable', 'integer', 'min:1', 'max:999'],
            'schedule_preset.unit' => ['required_if:schedule_preset.preset,every', 'nullable', 'in:days,weeks,months,years'],
            'schedule_preset.month' => ['required_if:schedule_preset.preset,yearly_on', 'nullable', 'integer', 'min:1', 'max:12'],
            'schedule_preset.day' => ['required_if:schedule_preset.preset,yearly_on', 'nullable', 'integer', 'min:1', 'max:31'],
            'schedule_preset.ordinal' => ['required_if:schedule_preset.preset,nth_weekday', 'nullable', 'integer', 'in:1,2,3,4,-1'],
            'schedule_preset.weekday' => ['required_if:schedule_preset.preset,nth_weekday', 'nullable', 'in:MO,TU,WE,TH,FR,SA,SU'],

            'next_due_at' => ['required_if:schedule_type,one_off', 'nullable', 'date'],
        ];
    }

    /**
     * Overridden by the update request: an already-calendar task may omit
     * the preset to keep its stored rule (covers "custom rule" tasks the
     * presets cannot re-express).
     *
     * @return list<string>
     */
    protected function schedulePresetPresenceRules(): array
    {
        return ['required_if:schedule_type,calendar', 'nullable'];
    }

    /**
     * The validated payload shaped into model-ready MaintenanceTask
     * attributes. The rule columns of the OTHER schedule types are
     * explicitly nulled so a type switch leaves no stale rule behind, and
     * the raw RRULE is produced server-side from the preset payload —
     * clients never send rules.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        $validated = $this->validated();

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'schedule_type' => $validated['schedule_type'],
            'reminder_lead_days' => $validated['reminder_lead_days'] ?? 7,
            ...$this->scheduleAttributes($validated),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function scheduleAttributes(array $validated): array
    {
        return match (MaintenanceScheduleType::from($validated['schedule_type'])) {
            MaintenanceScheduleType::Interval => [
                'interval_value' => $validated['interval_value'],
                'interval_unit' => $validated['interval_unit'],
                'rrule' => null,
            ],
            MaintenanceScheduleType::Calendar => [
                'interval_value' => null,
                'interval_unit' => null,
                'rrule' => isset($validated['schedule_preset'])
                    ? app(SchedulePresets::class)->toRrule($validated['schedule_preset'])
                    : $this->existingRrule(),
            ],
            MaintenanceScheduleType::OneOff => [
                'interval_value' => null,
                'interval_unit' => null,
                'rrule' => null,
                'next_due_at' => $validated['next_due_at'],
            ],
        };
    }

    /**
     * The stored rule of the task being edited — null on store, where no
     * task is bound. Pairs with schedulePresetPresenceRules(): only an
     * already-calendar task may omit the preset, and only then is this
     * fallback reached.
     */
    private function existingRrule(): ?string
    {
        $task = $this->route('maintenanceTask');

        return $task instanceof MaintenanceTask ? $task->rrule : null;
    }

    /**
     * A yearly_on day that can never exist in its month (Apr 31) would
     * produce an RRULE without occurrences. Feb 29 stays allowed — it
     * recurs on leap years, which is a legitimate (if eccentric) wish.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $preset = $this->input('schedule_preset');
                if (! is_array($preset) || ($preset['preset'] ?? null) !== 'yearly_on') {
                    return;
                }

                $month = (int) ($preset['month'] ?? 0);
                $day = (int) ($preset['day'] ?? 0);
                $maxDay = match ($month) {
                    4, 6, 9, 11 => 30,
                    2 => 29,
                    default => 31,
                };

                if ($month >= 1 && $month <= 12 && $day > $maxDay) {
                    $validator->errors()->add('schedule_preset.day', __('validation.custom.schedule_preset.impossible_date'));
                }
            },
        ];
    }
}
