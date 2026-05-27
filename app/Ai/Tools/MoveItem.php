<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FormatsItemLinks;
use App\Enums\ItemType;
use App\Models\Item;
use App\Services\InventorySearch;
use App\Services\Items\ItemWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class MoveItem implements Tool
{
    use FormatsItemLinks;

    public function __construct(
        private readonly ItemWriter $writer,
        private readonly InventorySearch $search,
    ) {}

    public function description(): string
    {
        return 'Move an item into a different room/container, or to the top level. Pass parent_name with the '
            .'destination place name (it is resolved for you) — or parent_id if you already know it. Only move to '
            .'the top level when the user explicitly asks. Confirm with the user before moving.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of the item to move.')->required(),
            'parent_id' => $schema->integer()->description('Id of the destination room/container, if you already know it.')->nullable(),
            'parent_name' => $schema->string()->description('Name of the destination room/container; it is matched for you. Use this when you know the place name but not its id.')->nullable(),
            'to_top_level' => $schema->boolean()->description('Set true ONLY when the user explicitly wants the item at the top level (no parent).')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $target = $this->resolveDestination($request, $item, $error);

        if ($error !== null) {
            return $error;
        }

        if ($target !== null && ($target->id === $item->id || $target->isDescendantOf($item))) {
            return 'Cannot move an item into itself or one of its own contents.';
        }

        $this->writer->move($item, $target?->id);

        return "Moved {$this->itemLink($item)} to ".($target !== null ? "{$this->itemLink($target)}." : 'the top level.');
    }

    /**
     * Work out the destination place from an explicit id, a name to resolve, or
     * an explicit top-level request. Returns the target item (null = top level)
     * and sets $error when the destination can't be determined (so we don't move).
     */
    private function resolveDestination(Request $request, Item $item, ?string &$error): ?Item
    {
        $error = null;

        if (($request['parent_id'] ?? null) !== null) {
            $target = Item::find((int) $request['parent_id']);

            if (! $target) {
                $error = "No destination with id {$request['parent_id']} exists.";
            }

            return $target;
        }

        $name = trim((string) ($request['parent_name'] ?? ''));

        if ($name !== '') {
            $target = $this->search->search($name, fn ($builder) => $builder->take(10)->get())
                ->first(fn (Item $candidate): bool => in_array($candidate->type, [ItemType::Room, ItemType::Container], true) && $candidate->id !== $item->id);

            if (! $target) {
                $error = "No room or container matches \"{$name}\". Ask the user which place they mean.";
            }

            return $target;
        }

        if ($request['to_top_level'] ?? false) {
            return null;
        }

        // Neither a destination nor an explicit top-level request — don't guess.
        $error = 'Specify where to move it: pass parent_name (the place), parent_id, or to_top_level=true.';

        return null;
    }
}
