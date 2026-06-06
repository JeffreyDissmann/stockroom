<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceEntryRequest;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use Illuminate\Http\RedirectResponse;

/**
 * Ad-hoc maintenance history on an item: entries without a schedule
 * ("replaced the brake pads"). Store is ad-hoc only — task completions go
 * through MaintenanceTaskController::complete, which also rolls the
 * schedule. Destroy removes any entry, task-bound or not: entries are
 * pure history, so deleting one never touches a task's schedule state.
 */
class MaintenanceEntryController extends Controller
{
    public function store(StoreMaintenanceEntryRequest $request, Item $item): RedirectResponse
    {
        $entry = $item->maintenanceEntries()->create($request->entryAttributes());

        $item->logMaintenanceActivity('maintenance_logged', ['notes' => $entry->notes]);

        return back();
    }

    public function destroy(Item $item, MaintenanceEntry $maintenanceEntry): RedirectResponse
    {
        // Removing history is itself history — log what the entry was
        // about (its task title, or the notes for ad-hoc entries).
        $maintenanceEntry->loadMissing('task');
        $item->logMaintenanceActivity('maintenance_entry_deleted', [
            'task_title' => $maintenanceEntry->task?->title,
            'notes' => $maintenanceEntry->notes,
        ]);

        $maintenanceEntry->delete();

        return back();
    }
}
