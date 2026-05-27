<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Item;
use App\Services\Items\ItemWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DeleteItem implements Tool
{
    public function __construct(private readonly ItemWriter $writer) {}

    public function description(): string
    {
        return 'Permanently delete an item (anything inside it moves up to the top level). This cannot be undone — '
            .'always get explicit confirmation from the user before calling this.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of the item to delete.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $name = $item->name;
        $this->writer->delete($item);

        return "Deleted \"{$name}\".";
    }
}
