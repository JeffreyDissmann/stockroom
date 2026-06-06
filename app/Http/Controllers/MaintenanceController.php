<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MaintenanceTask;
use App\Services\Maintenance\MaintenancePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The household-wide maintenance overview: every active schedule, soonest
 * due first, filterable by overdue / due-soon / all. "Due soon" means
 * inside the task's OWN reminder window — the same definition the item
 * page badge and the daily digest use, so the three surfaces never
 * disagree about what needs attention.
 *
 * Tasks are partitioned in PHP rather than SQL: the reminder window is
 * per-row (next_due_at - reminder_lead_days), date arithmetic on that is
 * not portable across pgsql/sqlite, and a household has tens of active
 * tasks, not thousands.
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenancePresenter $presenter) {}

    public function __invoke(Request $request): Response
    {
        $requested = $request->query('filter');
        $filter = in_array($requested, ['overdue', 'due-soon'], true) ? $requested : 'all';

        // Invariant: MaintenanceSchedule keeps every ACTIVE task with a
        // next_due_at (one-offs lose theirs only when archived). A null
        // here means manual DB surgery or a bug — show it (sorted last,
        // "No due date" badge) rather than silently hiding the anomaly;
        // the item page would surface it anyway.
        $tasks = MaintenanceTask::query()
            ->active()
            ->with('item')
            ->orderByRaw('(next_due_at is null) asc')
            ->orderBy('next_due_at')
            ->get();

        $overdue = $tasks->filter(fn (MaintenanceTask $task): bool => $task->isOverdue());
        $dueSoon = $tasks->filter(fn (MaintenanceTask $task): bool => $task->needsAttention() && ! $task->isOverdue());

        $visible = match ($filter) {
            'overdue' => $overdue,
            'due-soon' => $dueSoon,
            default => $tasks,
        };

        return Inertia::render('Maintenance', [
            'filter' => $filter,
            'counts' => [
                'all' => $tasks->count(),
                'overdue' => $overdue->count(),
                'due_soon' => $dueSoon->count(),
            ],
            'tasks' => $visible->values()->map(fn (MaintenanceTask $task): array => [
                ...$this->presenter->presentTask($task),
                'item' => [
                    'id' => $task->item->id,
                    'name' => $task->item->name,
                    'location' => $task->item->locationPath(),
                ],
            ]),
        ]);
    }
}
