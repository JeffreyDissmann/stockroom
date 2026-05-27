<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FormatsItemLinks;
use App\Models\Item;
use App\Services\Items\ItemWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class MoveItem implements Tool
{
    use FormatsItemLinks;

    public function __construct(private readonly ItemWriter $writer) {}

    public function description(): string
    {
        return 'Move an item into a different room/container (or to the top level). Confirm with the user before moving.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of the item to move.')->required(),
            'parent_id' => $schema->integer()->description('Id of the destination room/container. Omit for top level.')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $parentId = ($request['parent_id'] ?? null) !== null ? (int) $request['parent_id'] : null;

        if ($parentId !== null) {
            $target = Item::find($parentId);

            if (! $target) {
                return "No destination with id {$parentId} exists.";
            }
            if ($target->id === $item->id || $target->isDescendantOf($item)) {
                return 'Cannot move an item into itself or one of its own contents.';
            }
        }

        $this->writer->move($item, $parentId);

        return "Moved {$this->itemLink($item)} to ".($parentId ? "{$this->itemLink($target)}." : 'the top level.');
    }
}
