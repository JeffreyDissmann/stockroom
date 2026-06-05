<?php

declare(strict_types=1);

use App\Enums\MaintenanceIntervalUnit;
use App\Models\MaintenanceTask;
use App\Services\Maintenance\MaintenanceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->schedule = new MaintenanceSchedule;
});

describe('interval completion', function () {
    it('rolls the due date forward from the actual completion date', function () {
        $task = MaintenanceTask::factory()->interval(6, MaintenanceIntervalUnit::Months)->create([
            'next_due_at' => today()->subDays(20), // 20 days late
        ]);

        $completedAt = today();
        $this->schedule->applyCompletion($task, $completedAt);

        // Completion-relative: next due drifts with reality, NOT with the
        // old due date.
        expect($task->last_completed_at->toDateString())->toBe($completedAt->toDateString())
            ->and($task->next_due_at->toDateString())->toBe($completedAt->addMonthsNoOverflow(6)->toDateString())
            ->and($task->is_active)->toBeTrue();
    });

    it('handles each interval unit', function (MaintenanceIntervalUnit $unit, string $expected) {
        $task = MaintenanceTask::factory()->interval(2, $unit)->create();

        $this->schedule->applyCompletion($task, today()->setDate(2026, 1, 15));

        expect($task->next_due_at->toDateString())->toBe($expected);
    })->with([
        'days' => [MaintenanceIntervalUnit::Days, '2026-01-17'],
        'weeks' => [MaintenanceIntervalUnit::Weeks, '2026-01-29'],
        'months' => [MaintenanceIntervalUnit::Months, '2026-03-15'],
        'years' => [MaintenanceIntervalUnit::Years, '2028-01-15'],
    ]);

    it('does not overflow month-end boundaries', function () {
        $task = MaintenanceTask::factory()->interval(1, MaintenanceIntervalUnit::Months)->create();

        // 1 month after Jan 31 is Feb 28 (2026 is not a leap year), not Mar 3.
        $this->schedule->applyCompletion($task, today()->setDate(2026, 1, 31));

        expect($task->next_due_at->toDateString())->toBe('2026-02-28');
    });

    it('lands Feb 29 leap-year anniversaries on Feb 28 in common years', function () {
        $task = MaintenanceTask::factory()->interval(1, MaintenanceIntervalUnit::Years)->create();

        $this->schedule->applyCompletion($task, today()->setDate(2028, 2, 29));

        expect($task->next_due_at->toDateString())->toBe('2029-02-28');
    });

    it('refuses to skip — interval tasks stay due until done', function () {
        $task = MaintenanceTask::factory()->interval()->create();

        expect(fn () => $this->schedule->applySkip($task))->toThrow(InvalidArgumentException::class);
    });

    it('recomputes from the last completion when the rule is edited', function () {
        $task = MaintenanceTask::factory()->interval(6, MaintenanceIntervalUnit::Months)->create([
            'last_completed_at' => today()->subMonths(2),
        ]);

        // The rule changes from 6 to 3 months: due date re-derives from the
        // existing anchor, not from today.
        $task->interval_value = 3;
        $this->schedule->recompute($task);

        expect($task->next_due_at->toDateString())
            ->toBe(today()->subMonths(2)->addMonthsNoOverflow(3)->toDateString());
    });

    it('recomputes from today when nothing was ever completed', function () {
        $task = MaintenanceTask::factory()->interval(2, MaintenanceIntervalUnit::Weeks)->create([
            'last_completed_at' => null,
        ]);

        $this->schedule->recompute($task);

        expect($task->next_due_at->toDateString())->toBe(today()->addWeeks(2)->toDateString());
    });

    it('rejects an interval task with a missing rule', function () {
        $task = MaintenanceTask::factory()->interval()->create();
        $task->interval_value = null;

        expect(fn () => $this->schedule->applyCompletion($task, today()))->toThrow(LogicException::class);
    });
});

describe('one-off completion', function () {
    it('archives the task and clears the due date', function () {
        $task = MaintenanceTask::factory()->oneOff()->dueSoon()->create();

        $this->schedule->applyCompletion($task, today());

        expect($task->is_active)->toBeFalse()
            ->and($task->next_due_at)->toBeNull()
            ->and($task->last_completed_at->toDateString())->toBe(today()->toDateString());
    });

    it('refuses to skip', function () {
        $task = MaintenanceTask::factory()->oneOff()->dueSoon()->create();

        expect(fn () => $this->schedule->applySkip($task))->toThrow(InvalidArgumentException::class);
    });

    it('keeps the user-chosen due date on recompute', function () {
        $dueAt = today()->addDays(12);
        $task = MaintenanceTask::factory()->oneOff()->create(['next_due_at' => $dueAt]);

        $this->schedule->recompute($task);

        expect($task->next_due_at->toDateString())->toBe($dueAt->toDateString());
    });

    it('has no next occurrence', function () {
        $task = MaintenanceTask::factory()->oneOff()->dueSoon()->create();

        expect($this->schedule->nextDueAfter($task, today()->toImmutable()))->toBeNull();
    });
});

it('does not persist anything — the caller owns the transaction', function () {
    $task = MaintenanceTask::factory()->interval()->create(['next_due_at' => today()->subDay()]);
    $storedDueAt = $task->fresh()->next_due_at->toDateString();

    $this->schedule->applyCompletion($task, today());

    expect($task->fresh()->next_due_at->toDateString())->toBe($storedDueAt);
});
