<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MaintenanceTaskResource;
use App\Models\Item;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MaintenanceTaskController extends Controller
{
    /**
     * The maintenance schedules on one item, soonest due first (the model's
     * default order). Includes archived one-offs so HA can show history; the
     * resource flags each with is_active / is_overdue / due_in_days.
     */
    public function index(Item $item): AnonymousResourceCollection
    {
        return MaintenanceTaskResource::collection($item->maintenanceTasks);
    }
}
