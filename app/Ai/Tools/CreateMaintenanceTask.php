<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Services\Maintenance\MaintenancePresenter;
use App\Services\Maintenance\MaintenanceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Assistant-side twin of MaintenanceTaskController::store(), limited to
 * interval and one-off schedules. Calendar (RRULE) tasks stay UI-only:
 * their rules come from the curated preset builder, and letting the model
 * compose them freely invites rules the presets can't re-express.
 */
class CreateMaintenanceTask implements Tool
{
    use FormatsItemLinks;

    public function __construct(
        private readonly MaintenanceSchedule $schedule,
        private readonly MaintenancePresenter $presenter,
    ) {}

    public function description(): string
    {
        return 'Create a maintenance schedule on an item. Two schedule types: "interval" repeats N '
            .'days/weeks/months/years after each completion (batteries, filters, descaling); "one_off" '
            .'is due once on a fixed date and archives itself when done. Always confirm the task, item '
            .'and schedule with the user before calling this. Fixed calendar rules ("every first Sunday '
            .'in October") cannot be created here — point the user to the maintenance card on the item page.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('Id of the item the task belongs to.')->required(),
            'title' => $schema->string()->description('Short task title, e.g. "Replace batteries".')->required(),
            'schedule_type' => $schema->string()->enum(['interval', 'one_off'])->required(),
            'interval_value' => $schema->integer()->description('For interval tasks: repeat every N units (1-999).')->nullable(),
            'interval_unit' => $schema->string()->enum(['days', 'weeks', 'months', 'years'])->description('For interval tasks: the unit of interval_value.')->nullable(),
            'next_due_at' => $schema->string()->description('For one_off tasks: the due date, YYYY-MM-DD.')->nullable(),
            'description' => $schema->string()->description('Optional longer description.')->nullable(),
            'reminder_lead_days' => $schema->integer()->description('Days before the due date the reminders start. Defaults to 7.')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['item_id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $title = trim((string) ($request['title'] ?? ''));

        if ($title === '' || mb_strlen($title) > 255) {
            return 'A title (up to 255 characters) is required.';
        }

        $type = MaintenanceScheduleType::tryFrom((string) ($request['schedule_type'] ?? ''));

        if (! in_array($type, [MaintenanceScheduleType::Interval, MaintenanceScheduleType::OneOff], true)) {
            return 'schedule_type must be "interval" or "one_off". Calendar rules can only be created on the item page.';
        }

        $lead = $request['reminder_lead_days'] ?? 7;

        if (! is_numeric($lead) || (int) $lead < 0 || (int) $lead > 365) {
            return 'reminder_lead_days must be between 0 and 365.';
        }

        $rule = $this->scheduleAttributes($type, $request);

        if (is_string($rule)) {
            return $rule;
        }

        $task = $item->maintenanceTasks()->make([
            'title' => $title,
            'description' => $request['description'] ?? null,
            'schedule_type' => $type,
            'reminder_lead_days' => (int) $lead,
            ...$rule,
        ]);

        // Derives next_due_at from the rule before the single INSERT (a
        // one-off keeps its given date) — same flow as the controller.
        $this->schedule->recompute($task);
        $task->save();

        $item->logMaintenanceActivity('maintenance_task_added', ['task_title' => $task->title]);

        return "Created task #{$task->id} \"{$task->title}\" on {$this->itemLink($item)}: "
            ."{$this->presenter->scheduleSummary($task)}; first due {$task->next_due_at?->toDateString()}.";
    }

    /**
     * The schedule-rule attributes for the chosen type, or the model-facing
     * error string when its required fields are missing or out of range.
     *
     * @return array<string, mixed>|string
     */
    private function scheduleAttributes(MaintenanceScheduleType $type, Request $request): array|string
    {
        if ($type === MaintenanceScheduleType::Interval) {
            $value = $request['interval_value'] ?? null;
            $unit = MaintenanceIntervalUnit::tryFrom((string) ($request['interval_unit'] ?? ''));

            if (! is_numeric($value) || (int) $value < 1 || (int) $value > 999 || $unit === null) {
                return 'Interval tasks need interval_value (1-999) and interval_unit (days, weeks, months or years).';
            }

            return ['interval_value' => (int) $value, 'interval_unit' => $unit, 'rrule' => null];
        }

        try {
            $dueAt = CarbonImmutable::parse(trim((string) ($request['next_due_at'] ?? '')))->startOfDay();
        } catch (Throwable) {
            return 'One-off tasks need a next_due_at date — use YYYY-MM-DD.';
        }

        return ['interval_value' => null, 'interval_unit' => null, 'rrule' => null, 'next_due_at' => $dueAt];
    }
}
