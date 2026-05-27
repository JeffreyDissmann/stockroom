<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ItemType;
use App\Models\Item;
use App\Services\Items\ItemWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateItem implements Tool
{
    public function __construct(private readonly ItemWriter $writer) {}

    public function description(): string
    {
        return 'Create a new item, room, or container. Always confirm the name, type and location with the user before calling this.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Name of the item/room/container.')->required(),
            'type' => $schema->string()->enum(['room', 'container', 'item'])->description('room and container are places; item is a possession.')->required(),
            'parent_id' => $schema->integer()->description('Id of the room/container it goes inside. Omit for top level.')->nullable(),
            'description' => $schema->string()->nullable(),
            'quantity' => $schema->integer()->nullable(),
            'manufacturer' => $schema->string()->nullable(),
            'model_number' => $schema->string()->nullable(),
            'serial_number' => $schema->string()->nullable(),
            'purchase_price' => $schema->number()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $name = trim((string) ($request['name'] ?? ''));
        $type = $request['type'] ?? null;

        if ($name === '' || ! in_array($type, array_column(ItemType::cases(), 'value'), true)) {
            return 'A name and a valid type (room, container, item) are required.';
        }

        if (($parentId = $request['parent_id'] ?? null) !== null && ! Item::whereKey($parentId)->exists()) {
            return "No parent with id {$parentId} exists.";
        }

        $allowed = ['name', 'type', 'parent_id', 'description', 'quantity', 'manufacturer', 'model_number', 'serial_number', 'purchase_price'];
        $data = array_intersect_key($request->all(), array_flip($allowed));

        $item = $this->writer->create($data);

        return "Created {$item->type->value} #{$item->id} \"{$item->name}\".";
    }
}
