<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Enums\MaintenanceScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\CompleteMaintenanceTaskRequest;
use App\Http\Requests\Maintenance\StoreMaintenanceTaskRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceTaskRequest;
use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Services\Battery\BatteryService;
use App\Services\Maintenance\MaintenanceSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD for the maintenance schedules on an item. The FormRequests shape
 * the payload into model attributes (incl. preset → RRULE conversion);
 * every write funnels the scheduling fields through MaintenanceSchedule
 * so the stored next_due_at can never drift from the rule. Actions end
 * with back() — the user stays on the item page.
 */
class MaintenanceTaskController extends Controller
{
    public function __construct(
        private readonly MaintenanceSchedule $schedule,
        private readonly BatteryService $battery,
    ) {}

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

        $item->logMaintenanceActivity('maintenance_task_deleted', ['task_title' => $maintenanceTask->title]);

        return back();
    }

    /**
     * Mark the task done: record a history entry and roll the schedule
     * forward, atomically — a logged completion without a moved due date
     * (or vice versa) would corrupt the schedule's invariants.
     */
    public function complete(CompleteMaintenanceTaskRequest $request, Item $item, MaintenanceTask $maintenanceTask): RedirectResponse
    {
        // Archived one-offs are history. The UI never offers this, so the
        // realistic trigger is a stale page (someone else completed it) —
        // a validation error redirects back and re-renders current state.
        // (Not abort(409): Inertia reserves 409 for location redirects.)
        if (! $maintenanceTask->is_active) {
            throw ValidationException::withMessages([
                'task' => __('validation.custom.maintenance_task.archived'),
            ]);
        }

        // Completing a battery reminder IS a battery change: swap the cycle
        // and record the completion through the one owner of that loop.
        if ($maintenanceTask->schedule_type === MaintenanceScheduleType::Forecast) {
            $entry = $request->entryAttributes();
            $this->battery->changeBattery($item, $entry['completed_at'], $entry['notes'], $entry['performed_by']);

            return back();
        }

        DB::transaction(function () use ($request, $item, $maintenanceTask): void {
            $entry = $item->maintenanceEntries()->create([
                'maintenance_task_id' => $maintenanceTask->id,
                ...$request->entryAttributes(),
            ]);

            $this->schedule->applyCompletion($maintenanceTask, $entry->completed_at);
            $maintenanceTask->save();

            $item->logMaintenanceActivity('maintenance_completed', [
                'task_title' => $maintenanceTask->title,
                'cost' => $entry->cost,
            ]);
        });

        return back();
    }

    /**
     * Skip the current occurrence of a calendar task — the due date
     * advances without a completion record. State guards surface as
     * validation errors, mirroring complete().
     */
    public function skip(Item $item, MaintenanceTask $maintenanceTask): RedirectResponse
    {
        if (! $maintenanceTask->is_active || ! $maintenanceTask->schedule_type->isSkippable()) {
            throw ValidationException::withMessages([
                'task' => __('validation.custom.maintenance_task.not_skippable'),
            ]);
        }

        $this->schedule->applySkip($maintenanceTask);
        $maintenanceTask->save();

        $item->logMaintenanceActivity('maintenance_skipped', ['task_title' => $maintenanceTask->title]);

        return back();
    }
}
