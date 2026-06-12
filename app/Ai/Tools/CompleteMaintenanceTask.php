<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Ai\Concerns\ParsesCompletionDates;
use App\Enums\MaintenanceScheduleType;
use App\Models\MaintenanceTask;
use App\Services\Battery\BatteryService;
use App\Services\Maintenance\MaintenancePresenter;
use App\Services\Maintenance\MaintenanceSchedule;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Assistant-side twin of MaintenanceTaskController::complete(): records a
 * history entry and rolls the schedule forward atomically. Guards return
 * plain strings (the tool-call convention) instead of validation errors.
 */
class CompleteMaintenanceTask implements Tool
{
    use FormatsItemLinks;
    use ParsesCompletionDates;

    public function __construct(
        private readonly MaintenanceSchedule $schedule,
        private readonly MaintenancePresenter $presenter,
        private readonly BatteryService $battery,
    ) {}

    public function description(): string
    {
        return 'Mark a maintenance task as completed: records a history entry and rolls the next due '
            .'date forward (one-off tasks archive themselves). Always confirm with the user before '
            .'calling this. The completion date may be in the past but never in the future.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The maintenance task id (as returned by maintenance_overview or get_item).')->required(),
            'completed_at' => $schema->string()->description('Date the work was actually done, YYYY-MM-DD. Defaults to today.')->nullable(),
            'notes' => $schema->string()->description('Optional notes for the history entry.')->nullable(),
            'cost' => $schema->number()->description('Optional cost of the work.')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $task = MaintenanceTask::with('item')->find((int) ($request['task_id'] ?? 0));

        if (! $task) {
            return 'No maintenance task found with that id.';
        }

        if (! $task->is_active) {
            return "Task #{$task->id} \"{$task->title}\" is archived — it was already completed and cannot be completed again.";
        }

        $completedAt = $this->parseCompletedAt($request['completed_at'] ?? null);

        if (is_string($completedAt)) {
            return $completedAt;
        }

        $cost = $request['cost'] ?? null;

        if ($cost !== null && (! is_numeric($cost) || (float) $cost < 0)) {
            return 'The cost must be a non-negative number.';
        }

        // Completing a battery reminder IS a battery change: swap the cycle
        // and record the completion through the one owner of that loop.
        if ($task->schedule_type === MaintenanceScheduleType::Forecast) {
            $this->battery->changeBattery($task->item, $completedAt, $request['notes'] ?? null, auth()->id());
            $task->refresh();

            return "Recorded a battery change for task #{$task->id} \"{$task->title}\" on {$this->itemLink($task->item)} "
                ."for {$completedAt->toDateString()}. A fresh battery cycle has started; the next due date will be "
                .'predicted as new level readings arrive.';
        }

        DB::transaction(function () use ($request, $task, $completedAt, $cost): void {
            $entry = $task->item->maintenanceEntries()->create([
                'maintenance_task_id' => $task->id,
                'performed_by' => auth()->id(),
                'completed_at' => $completedAt,
                'notes' => $request['notes'] ?? null,
                'cost' => $cost,
            ]);

            $this->schedule->applyCompletion($task, $entry->completed_at);
            $task->save();

            $task->item->logMaintenanceActivity('maintenance_completed', [
                'task_title' => $task->title,
                'cost' => $entry->cost,
            ]);
        });

        $outcome = $task->schedule_type === MaintenanceScheduleType::OneOff
            ? 'The one-off task is now archived.'
            : "Next due: {$task->next_due_at?->toDateString()} ({$this->presenter->dueLabel($task)}).";

        return "Recorded completion of task #{$task->id} \"{$task->title}\" on {$this->itemLink($task->item)} "
            ."for {$completedAt->toDateString()}. {$outcome}";
    }
}
