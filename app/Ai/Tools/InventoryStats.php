<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class InventoryStats implements Tool
{
    public function description(): string
    {
        return 'Aggregate the inventory: count entries or sum their value (purchase price of owned, unsold items), '
            .'optionally restricted to one type and/or grouped by type or tag. Use for "how many…" and '
            .'"what did I spend / total value" questions. Types are: room (a place), container (holds things), '
            .'item (an actual possession). NOTE: an unfiltered count includes rooms and containers, so for '
            .'"how many items do I own" pass type="item".';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(['count', 'value'])->description('"count" of entries, or "value" = sum of purchase prices of unsold items.')->required(),
            'type' => $schema->string()->enum(['room', 'container', 'item'])->description('Optional: restrict to a single type. Use "item" for actual possessions (excludes rooms and containers).')->nullable(),
            'group_by' => $schema->string()->enum(['type', 'tag'])->description('Optional grouping: by type or by tag. Omit for an overall total.')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $metric = ($request['metric'] ?? 'count') === 'value' ? 'value' : 'count';
        $groupBy = in_array($request['group_by'] ?? null, ['type', 'tag'], true) ? $request['group_by'] : null;
        $type = ItemType::tryFrom((string) ($request['type'] ?? ''));

        if ($groupBy === 'type') {
            $rows = Item::query()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->when($metric === 'value', fn ($q) => $q->whereNull('sold_date'))
                ->get(['type', 'purchase_price'])
                ->groupBy(fn (Item $item): string => $item->type->value)
                ->sortKeys()
                ->map(fn ($group): string => $metric === 'value' ? $this->money($group->sum('purchase_price')) : (string) $group->count());

            return $rows->map(fn (string $stat, string $type): string => "{$type}: {$stat}")->implode("\n") ?: 'No items.';
        }

        if ($groupBy === 'tag') {
            // Aggregate over the items relationship with subqueries (withCount/withSum)
            // so Eloquent walks the item_tag pivot for us — no explicit join, no raw SQL.
            $constrain = function ($query) use ($type, $metric): void {
                $query->when($type, fn ($q) => $q->where('type', $type))
                    ->when($metric === 'value', fn ($q) => $q->whereNull('sold_date'));
            };

            $tags = Tag::query()
                ->withCount(['items as match_count' => $constrain])
                ->when($metric === 'value', fn ($q) => $q->withSum(['items as match_value' => $constrain], 'purchase_price'))
                ->orderBy('name')->get()
                ->filter(fn (Tag $tag): bool => $tag->match_count > 0);

            return $tags->map(fn (Tag $tag): string => "{$tag->name}: ".($metric === 'value' ? $this->money($tag->match_value ?? 0) : $tag->match_count))->implode("\n") ?: 'No tagged items.';
        }

        $label = $type ? "{$type->value}s" : 'entries';

        if ($metric === 'value') {
            $total = Item::query()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->whereNull('sold_date')->sum('purchase_price');

            return "Total value of owned {$label}: ".$this->money($total);
        }

        return "Total {$label}: ".Item::query()->when($type, fn ($q) => $q->where('type', $type))->count();
    }

    /**
     * Format a numeric value with the household currency code so the model never guesses the symbol.
     */
    private function money(int|float|string $value): string
    {
        return $value.' '.config('stockroom.currency.code', 'USD');
    }
}
