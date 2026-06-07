<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Models\MaintenanceTask;
use App\Services\Maintenance\MaintenancePresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class MaintenanceOverview implements Tool
{
    use FormatsItemLinks;

    public function __construct(private readonly MaintenancePresenter $presenter) {}

    public function description(): string
    {
        return 'List the household\'s maintenance tasks with their items and due dates. By DEFAULT '
            .'this returns only tasks needing attention (overdue or inside their reminder window) — '
            .'what users mean by "what maintenance is due / overdue". Pass scope=all to list every '
            .'active maintenance schedule regardless of due date.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'scope' => $schema->string()->enum(['attention', 'all'])
                ->description('Defaults to "attention" (overdue + due soon). Use "all" for every active schedule.')
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $tasks = ($request['scope'] ?? null) === 'all'
            ? MaintenanceTask::query()->active()->with('item')->orderBy('next_due_at')->get()
            : MaintenanceTask::needingAttention();

        if ($tasks->isEmpty()) {
            return ($request['scope'] ?? null) === 'all'
                ? 'No active maintenance schedules exist.'
                : 'No maintenance needs attention — nothing is overdue or coming up.';
        }

        return $tasks
            ->map(fn (MaintenanceTask $task): string => $this->line($task))
            ->implode("\n");
    }

    /**
     * One compact model-facing line per task: id (for follow-up tool calls),
     * title, item link, due state, recurrence rule and last completion.
     */
    private function line(MaintenanceTask $task): string
    {
        $parts = [
            $this->presenter->dueLabel($task).' ('.$task->next_due_at?->toDateString().')',
            $this->presenter->scheduleSummary($task),
            'last completed: '.($task->last_completed_at?->toDateString() ?? 'never'),
        ];

        return "- Task #{$task->id} \"{$task->title}\" on {$this->itemLink($task->item)} — ".implode('; ', $parts);
    }
}
