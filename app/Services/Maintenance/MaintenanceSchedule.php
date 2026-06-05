<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\MaintenanceTask;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use LogicException;

/**
 * The single writer of MaintenanceTask scheduling state. All transitions
 * of next_due_at / last_completed_at / is_active go through here so the
 * stored next_due_at projection can never drift from the schedule rule.
 *
 * Pure in-memory date math: methods mutate the given task's attributes but
 * never persist — the caller decides the transaction boundary.
 *
 * All math is date-only in the app timezone (today(), no times): a task is
 * due on a calendar day, not at an instant.
 */
class MaintenanceSchedule
{
    /**
     * The next due date strictly after $from, per the task's rule. Null for
     * one-offs — they have no "next" beyond their user-chosen due date.
     */
    public function nextDueAfter(MaintenanceTask $task, CarbonImmutable $from): ?CarbonImmutable
    {
        return match ($task->schedule_type) {
            MaintenanceScheduleType::Interval => $this->intervalUnit($task)->addTo($from, $this->intervalValue($task)),
            MaintenanceScheduleType::Calendar => $this->nextCalendarOccurrenceAfter($task, $from),
            MaintenanceScheduleType::OneOff => null,
        };
    }

    /**
     * Record a completion: anchor last_completed_at and roll next_due_at
     * forward. Interval tasks roll from the actual completion date (a late
     * battery change shifts the next one); calendar tasks stay anchored to
     * the calendar; one-offs archive themselves.
     */
    public function applyCompletion(MaintenanceTask $task, CarbonInterface $completedAt): void
    {
        $completedOn = $completedAt->toImmutable()->startOfDay();
        $task->last_completed_at = $completedOn;

        $task->next_due_at = match ($task->schedule_type) {
            MaintenanceScheduleType::Interval => $this->nextDueAfter($task, $completedOn),
            // Completing early/late must not pull occurrences off the
            // calendar — the next due is the next occurrence after today.
            MaintenanceScheduleType::Calendar => $this->nextCalendarOccurrenceAfter($task, today()->toImmutable()),
            MaintenanceScheduleType::OneOff => null,
        };

        if ($task->schedule_type === MaintenanceScheduleType::OneOff) {
            $task->is_active = false;
        }
    }

    /**
     * Skip the current occurrence of a calendar task: advance next_due_at
     * without recording a completion. Only meaningful on a fixed cadence —
     * interval tasks just stay due until done.
     */
    public function applySkip(MaintenanceTask $task): void
    {
        if (! $task->schedule_type->isSkippable()) {
            throw new InvalidArgumentException("Only calendar tasks can skip an occurrence; this task is {$task->schedule_type->value}.");
        }

        // Advance from the currently stored due date so consecutive skips
        // step occurrence by occurrence; fall back to today for a task
        // whose due date is somehow missing.
        $from = ($task->next_due_at ?? today())->toImmutable()->startOfDay();
        $task->next_due_at = $this->nextCalendarOccurrenceAfter($task, $from);
    }

    /**
     * (Re)derive next_due_at after the schedule rule was created or edited.
     * One-offs keep their user-chosen due date — there is nothing to derive.
     */
    public function recompute(MaintenanceTask $task): void
    {
        $task->next_due_at = match ($task->schedule_type) {
            // Anchor on the last completion when there is one, else the rule
            // starts counting today.
            MaintenanceScheduleType::Interval => $this->nextDueAfter(
                $task,
                ($task->last_completed_at ?? today())->toImmutable()->startOfDay(),
            ),
            MaintenanceScheduleType::Calendar => $this->nextCalendarOccurrenceAfter($task, today()->toImmutable()),
            MaintenanceScheduleType::OneOff => $task->next_due_at,
        };
    }

    /**
     * RRULE evaluation lands with the calendar schedule type (next step);
     * the seam exists so the public API is already complete.
     */
    private function nextCalendarOccurrenceAfter(MaintenanceTask $task, CarbonImmutable $from): CarbonImmutable
    {
        throw new LogicException('Calendar (RRULE) schedules are not implemented yet.');
    }

    private function intervalValue(MaintenanceTask $task): int
    {
        return $task->interval_value
            ?? throw new LogicException("Interval task #{$task->id} has no interval_value.");
    }

    private function intervalUnit(MaintenanceTask $task): MaintenanceIntervalUnit
    {
        return $task->interval_unit
            ?? throw new LogicException("Interval task #{$task->id} has no interval_unit.");
    }
}
