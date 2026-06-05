<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Enums\MaintenanceScheduleType;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use Carbon\CarbonImmutable;

/**
 * Serialises maintenance tasks and entries for Inertia props. Shared by
 * the item Show page, the global maintenance page and the dashboard card
 * so the row shape (and the localized schedule summary) stays identical
 * everywhere.
 */
class MaintenancePresenter
{
    public function __construct(private readonly SchedulePresets $presets) {}

    /**
     * @return array<string, mixed>
     */
    public function presentTask(MaintenanceTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'schedule_type' => $task->schedule_type->value,
            'schedule_summary' => $this->scheduleSummary($task),
            'next_due_at' => $task->next_due_at?->toDateString(),
            'due_in_days' => $task->dueInDays(),
            'is_overdue' => $task->isOverdue(),
            'last_completed_at' => $task->last_completed_at?->toDateString(),
            'reminder_lead_days' => $task->reminder_lead_days,
            'can_skip' => $task->is_active && $task->schedule_type->isSkippable(),
            // Raw rule fields so the edit dialog can re-hydrate its builder;
            // schedule_preset is null for rules beyond the presets, which
            // the dialog renders read-only (schedule_summary still shows).
            'interval_value' => $task->interval_value,
            'interval_unit' => $task->interval_unit?->value,
            'schedule_preset' => $task->rrule !== null ? $this->presets->toPayload($task->rrule) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function presentEntry(MaintenanceEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'completed_at' => $entry->completed_at->toDateString(),
            'notes' => $entry->notes,
            'cost' => $entry->cost,
            // Null performer = the user has since been deleted.
            'performed_by_name' => $entry->performer?->name,
            // Null title = ad-hoc entry (or its task was deleted).
            'task_title' => $entry->task?->title,
        ];
    }

    /**
     * One localized line describing the recurrence rule, e.g. "Every 6
     * months after completion" or "Every first Sunday in March".
     */
    public function scheduleSummary(MaintenanceTask $task): string
    {
        return match ($task->schedule_type) {
            MaintenanceScheduleType::Interval => __('maintenance.schedule.interval', [
                'every' => trans_choice("maintenance.schedule.every.{$task->interval_unit->value}", $task->interval_value ?? 1),
            ]),
            MaintenanceScheduleType::Calendar => $this->calendarSummary($task),
            MaintenanceScheduleType::OneOff => __('maintenance.schedule.one_off'),
        };
    }

    private function calendarSummary(MaintenanceTask $task): string
    {
        $payload = $task->rrule !== null ? $this->presets->toPayload($task->rrule) : null;

        return match ($payload['preset'] ?? null) {
            'every' => __('maintenance.schedule.calendar_every', [
                'every' => trans_choice("maintenance.schedule.every.{$payload['unit']}", $payload['interval']),
            ]),
            'yearly_on' => __('maintenance.schedule.yearly_on', [
                // Year 2000 is arbitrary — only the localized day + month
                // render. A leap year, so Feb 29 formats too.
                'date' => CarbonImmutable::create(2000, $payload['month'], $payload['day'])
                    ->translatedFormat(__('maintenance.schedule.yearly_on_date_format')),
            ]),
            'nth_weekday' => $this->nthWeekdaySummary($payload),
            default => __('maintenance.schedule.custom'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function nthWeekdaySummary(array $payload): string
    {
        $parameters = [
            'ordinal' => __('maintenance.schedule.ordinals.'.$payload['ordinal']),
            'weekday' => __('maintenance.schedule.weekdays.'.$payload['weekday']),
        ];

        if ($payload['month'] === null) {
            return __('maintenance.schedule.nth_weekday_monthly', $parameters);
        }

        return __('maintenance.schedule.nth_weekday_yearly', [
            ...$parameters,
            'month' => CarbonImmutable::create(2000, $payload['month'], 1)->translatedFormat('F'),
        ]);
    }
}
