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
use Recurr\Rule;
use Recurr\Transformer\ArrayTransformer;
use Recurr\Transformer\ArrayTransformerConfig;
use Recurr\Transformer\Constraint\AfterConstraint;
use RuntimeException;

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
            // calendar. The pending occurrence is spent either way, so roll
            // from whichever is later: today (catches up an overdue task)
            // or the stored due date (an early completion consumes the
            // upcoming occurrence instead of leaving it to nag).
            MaintenanceScheduleType::Calendar => $this->nextCalendarOccurrenceAfter($task, $this->calendarCompletionAnchor($task)),
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
            // Inclusive: a freshly created/edited rule that matches today IS
            // due today — unlike after a completion or a skip, where the
            // current occurrence is spent and only the future counts.
            MaintenanceScheduleType::Calendar => $this->nextCalendarOccurrenceAfter($task, today()->toImmutable(), inclusive: true),
            MaintenanceScheduleType::OneOff => $task->next_due_at,
        };
    }

    private function calendarCompletionAnchor(MaintenanceTask $task): CarbonImmutable
    {
        $today = today()->toImmutable()->startOfDay();
        $dueAt = $task->next_due_at?->toImmutable()->startOfDay();

        return $dueAt !== null && $dueAt->gt($today) ? $dueAt : $today;
    }

    /**
     * First RRULE occurrence after $from, evaluated by recurr. The rule is
     * anchored at the task's creation date (today for unsaved tasks) — the
     * anchor must be STABLE across completions, otherwise "every 3 months"
     * would re-phase to the completion date and become an interval schedule
     * in disguise.
     */
    private function nextCalendarOccurrenceAfter(MaintenanceTask $task, CarbonImmutable $from, bool $inclusive = false): CarbonImmutable
    {
        $rrule = $task->rrule
            ?? throw new LogicException("Calendar task #{$task->id} has no rrule.");

        $anchor = ($task->created_at?->toImmutable() ?? today()->toImmutable())->startOfDay();
        $rule = new Rule($rrule, $anchor, null, config('app.timezone'));

        // We only ever need the first matching occurrence; a tiny virtual
        // limit stops the transformer from materialising hundreds of them.
        // recurr's own 300-iteration brake terminates impossible rules
        // (e.g. Feb 31) with an empty collection, which we surface loudly.
        $config = new ArrayTransformerConfig;
        $config->setVirtualLimit(1);

        $first = (new ArrayTransformer($config))
            ->transform($rule, new AfterConstraint($from->startOfDay(), $inclusive), countConstraintFailures: false)
            ->first();

        if ($first === false || $first === null) {
            throw new RuntimeException("RRULE '{$rrule}' yields no occurrence after {$from->toDateString()}.");
        }

        return CarbonImmutable::instance($first->getStart())->startOfDay();
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
