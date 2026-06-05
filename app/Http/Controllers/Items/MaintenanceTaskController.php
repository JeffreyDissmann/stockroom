<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceTaskRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTaskRequest;
use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Services\Maintenance\MaintenanceSchedule;
use Illuminate\Http\RedirectResponse;

/**
 * CRUD for the maintenance schedules on an item. The FormRequests shape
 * the payload into model attributes (incl. preset → RRULE conversion);
 * every write funnels the scheduling fields through MaintenanceSchedule
 * so the stored next_due_at can never drift from the rule. Actions end
 * with back() — the user stays on the item page.
 */
class MaintenanceTaskController extends Controller
{
    public function __construct(private readonly MaintenanceSchedule $schedule) {}

    public function store(StoreMaintenanceTaskRequest $request, Item $item): RedirectResponse
    {
        $task = $item->maintenanceTasks()->make($request->taskAttributes());

        // Derives next_due_at before the single INSERT. The calendar anchor
        // (created_at) is still null here, so the rule anchors on today —
        // which IS the creation date.
        $this->schedule->recompute($task);
        $task->save();

        $item->logMaintenanceActivity('maintenance_task_added', ['task_title' => $task->title]);

        return back();
    }

    public function update(UpdateMaintenanceTaskRequest $request, Item $item, MaintenanceTask $maintenanceTask): RedirectResponse
    {
        $maintenanceTask->fill($request->taskAttributes());

        // Only re-derive the due date when the rule itself changed — a
        // title-only edit must not pull a skipped calendar task back to
        // its nearer occurrence.
        if ($maintenanceTask->isDirty(['schedule_type', 'interval_value', 'interval_unit', 'rrule'])) {
            $this->schedule->recompute($maintenanceTask);
        }

        $maintenanceTask->save();

        return back();
    }

    public function destroy(Item $item, MaintenanceTask $maintenanceTask): RedirectResponse
    {
        // Entries survive as ad-hoc history via the FK's nullOnDelete.
        $maintenanceTask->delete();

        return back();
    }
}
