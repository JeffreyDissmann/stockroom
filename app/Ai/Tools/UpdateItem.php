<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Models\Item;
use App\Services\Items\ItemWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateItem implements Tool
{
    use FormatsItemLinks;

    public function __construct(private readonly ItemWriter $writer) {}

    public function description(): string
    {
        return 'Update fields of an existing item (only the fields you pass change). Confirm the change with the user first. '
            .'To move an item use move_item; to tag it use assign_tags.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The item id.')->required(),
            'name' => $schema->string()->nullable(),
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
        $item = Item::find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $editable = ['name', 'description', 'quantity', 'manufacturer', 'model_number', 'serial_number', 'purchase_price'];
        $data = array_intersect_key($request->all(), array_flip($editable));

        if ($data === []) {
            return 'No fields were provided to update.';
        }

        $this->writer->update($item, $data);

        return "Updated #{$item->id} {$this->itemLink($item)} (".implode(', ', array_keys($data)).').';
    }
}
