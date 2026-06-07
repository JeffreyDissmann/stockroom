<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteMaintenanceTaskRequest;
use App\Http\Requests\Api\V1\StoreMaintenanceTaskRequest;
use App\Http\Resources\Api\V1\MaintenanceTaskResource;
use App\Models\Item;
use App\Models\MaintenanceTask;
use App\Services\Maintenance\MaintenanceSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceTaskController extends Controller
{
    public function __construct(private readonly MaintenanceSchedule $schedule) {}

    /**
     * The maintenance schedules on one item, soonest due first (the model's
     * default order). Includes archived one-offs so HA can show history; the
     * resource flags each with is_active / is_overdue / due_in_days.
     */
    public function index(Item $item): AnonymousResourceCollection
    {
        return MaintenanceTaskResource::collection($item->maintenanceTasks);
    }

    /**
     * Create a maintenance schedule on the item. next_due_at is derived from
     * the rule before the single INSERT — same flow as the web controller.
     */
    public function store(StoreMaintenanceTaskRequest $request, Item $item): JsonResponse
    {
        $task = $item->maintenanceTasks()->make($request->taskAttributes());

        $this->schedule->recompute($task);
        $task->save();

        $item->logMaintenanceActivity('maintenance_task_added', ['task_title' => $task->title]);

        return (new MaintenanceTaskResource($task))->response()->setStatusCode(201);
    }

    /**
     * Mark a task done: record a history entry and roll the schedule forward,
     * atomically — mirrors the web controller. The task is resolved
     * standalone (not nested under its item) so HA can complete by task id.
     */
    public function complete(CompleteMaintenanceTaskRequest $request, MaintenanceTask $maintenanceTask): MaintenanceTaskResource
    {
        if (! $maintenanceTask->is_active) {
            throw ValidationException::withMessages([
                'task' => __('validation.custom.maintenance_task.archived'),
            ]);
        }

        DB::transaction(function () use ($request, $maintenanceTask): void {
            $entry = $maintenanceTask->item->maintenanceEntries()->create([
                'maintenance_task_id' => $maintenanceTask->id,
                ...$request->entryAttributes(),
            ]);

            $this->schedule->applyCompletion($maintenanceTask, $entry->completed_at);
            $maintenanceTask->save();

            $maintenanceTask->item->logMaintenanceActivity('maintenance_completed', [
                'task_title' => $maintenanceTask->title,
                'cost' => $entry->cost,
            ]);
        });

        return new MaintenanceTaskResource($maintenanceTask);
    }
}
