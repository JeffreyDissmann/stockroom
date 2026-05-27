<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FormatsItemLinks;
use App\Models\CustomFieldValue;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetItem implements Tool
{
    use FormatsItemLinks;

    public function description(): string
    {
        return 'Get the full details of a single inventory item by its id: type, location, quantity, '
            .'manufacturer/model/serial, purchase and warranty info, tags, and custom fields.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The item id (as returned by search_items).')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::with(['tags', 'customFieldValues.field'])->find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $lines = [
            "#{$item->id} {$this->itemLink($item)} ({$item->type->value})",
            'Location: '.($item->locationPath() ?: 'top level'),
        ];

        if ($item->description) {
            $lines[] = "Description: {$item->description}";
        }

        $details = array_filter([
            'Quantity' => $item->quantity,
            'Manufacturer' => $item->manufacturer,
            'Model' => $item->model_number,
            'Serial' => $item->serial_number,
            'Purchased from' => $item->purchased_from,
            'Purchase date' => $item->purchase_date?->toDateString(),
            'Purchase price' => $item->purchase_price,
            'Warranty until' => $item->lifetime_warranty ? 'lifetime' : $item->warranty_expires?->toDateString(),
            'Sold to' => $item->sold_to,
            'Sold price' => $item->sold_price,
            'Sold date' => $item->sold_date?->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');

        foreach ($details as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }

        if ($item->tags->isNotEmpty()) {
            $lines[] = 'Tags: '.$item->tags->map(fn (Tag $t): string => $t->name)->implode(', ');
        }

        foreach ($item->customFieldValues as $value) {
            /** @var CustomFieldValue $value */
            if ($value->value !== null && $value->value !== '') {
                $lines[] = "{$value->field->name}: {$value->value}";
            }
        }

        return implode("\n", $lines);
    }
}
