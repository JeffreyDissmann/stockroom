<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Ai\Concerns\ParsesCompletionDates;
use App\Models\Item;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Assistant-side twin of MaintenanceEntryController::store(): an ad-hoc
 * history record without a schedule. Unlike a task completion, the notes
 * are required — without a task title they are the only thing saying what
 * was done.
 */
class LogMaintenanceEntry implements Tool
{
    use FormatsItemLinks;
    use ParsesCompletionDates;

    public function description(): string
    {
        return 'Record a one-time maintenance or repair on an item as a history entry ("repaired the '
            .'drawer handle") — for work that never had a schedule. To complete a scheduled task use '
            .'complete_maintenance_task instead. Always confirm with the user before calling this. '
            .'The date may be in the past but never in the future.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('Id of the item the work was done on.')->required(),
            'notes' => $schema->string()->description('What was done, e.g. "Replaced the brake pads".')->required(),
            'completed_at' => $schema->string()->description('Date the work was done, YYYY-MM-DD. Defaults to today.')->nullable(),
            'cost' => $schema->number()->description('Optional cost of the work.')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['item_id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $notes = trim((string) ($request['notes'] ?? ''));

        if ($notes === '' || mb_strlen($notes) > 2000) {
            return 'Notes describing the work (up to 2000 characters) are required.';
        }

        $completedAt = $this->parseCompletedAt($request['completed_at'] ?? null);

        if (is_string($completedAt)) {
            return $completedAt;
        }

        $cost = $request['cost'] ?? null;

        if ($cost !== null && (! is_numeric($cost) || (float) $cost < 0)) {
            return 'The cost must be a non-negative number.';
        }

        $entry = $item->maintenanceEntries()->create([
            'maintenance_task_id' => null,
            'performed_by' => auth()->id(),
            'completed_at' => $completedAt,
            'notes' => $notes,
            'cost' => $cost,
        ]);

        $item->logMaintenanceActivity('maintenance_logged', ['notes' => $entry->notes]);

        return "Logged maintenance on {$this->itemLink($item)} for {$completedAt->toDateString()}: {$notes}";
    }
}
