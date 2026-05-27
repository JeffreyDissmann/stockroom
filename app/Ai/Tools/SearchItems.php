<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FormatsItemLinks;
use App\Models\Item;
use App\Services\InventorySearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SearchItems implements Tool
{
    use FormatsItemLinks;

    public function __construct(private readonly InventorySearch $search) {}

    public function description(): string
    {
        return 'Search the household inventory by free text (item names, descriptions, brands, locations). '
            .'Returns matching items with their id, type, location and value. Use this to find or locate things.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('What to look for, e.g. "cordless drill" or "things from IKEA".')->required(),
            'limit' => $schema->integer()->description('Maximum results (default 10, max 25).')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $query = trim((string) ($request['query'] ?? ''));

        if ($query === '') {
            return 'No search query was provided.';
        }

        $limit = max(1, min(25, (int) ($request['limit'] ?? 10)));
        $items = $this->search->search($query, fn ($builder) => $builder->take($limit)->get());

        if ($items->isEmpty()) {
            return "No items match \"{$query}\".";
        }

        return $items->map(function (Item $item): string {
            $parts = ["#{$item->id} {$this->itemLink($item)} ({$item->type->value})"];

            if (($path = $item->locationPath()) !== '') {
                $parts[] = "in {$path}";
            }
            if ($item->purchase_price !== null) {
                $parts[] = "value {$item->purchase_price}";
            }

            return implode(' — ', $parts);
        })->implode("\n");
    }
}
