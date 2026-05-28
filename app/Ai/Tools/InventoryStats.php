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
        return 'Aggregate the inventory: count or sum value (purchase price of owned, unsold items). '
            .'By DEFAULT this counts actual possessions only (type=item) — what users mean by "how many '
            .'items / what did I spend". Pass type=room or type=container to count those instead, or '
            .'type=all to include every entry. group_by=type or group_by=tag returns a breakdown.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'metric' => $schema->string()->enum(['count', 'value'])->description('"count" of entries, or "value" = sum of purchase prices of unsold items.')->required(),
            'type' => $schema->string()->enum(['item', 'room', 'container', 'all'])->description('Defaults to "item" (possessions). Use "room"/"container" for places, "all" to include everything.')->nullable(),
            'group_by' => $schema->string()->enum(['type', 'tag'])->description('Optional grouping: by type or by tag (overrides any type filter for the grouping).')->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $metric = ($request['metric'] ?? 'count') === 'value' ? 'value' : 'count';
        $groupBy = in_array($request['group_by'] ?? null, ['type', 'tag'], true) ? $request['group_by'] : null;
        $type = $this->resolveType($request['type'] ?? null, $groupBy);

        return match ($groupBy) {
            'type' => $this->groupedByType($type, $metric),
            'tag' => $this->groupedByTag($type, $metric),
            default => $this->total($type, $metric),
        };
    }

    /**
     * Per-type breakdown of count or summed value.
     */
    private function groupedByType(?ItemType $type, string $metric): string
    {
        $rows = Item::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($metric === 'value', fn ($q) => $q->whereNull('sold_date'))
            ->get(['type', 'purchase_price'])
            ->groupBy(fn (Item $item): string => $item->type->value)
            ->sortKeys()
            ->map(fn ($group): string => $metric === 'value' ? $this->money($group->sum('purchase_price')) : (string) $group->count());

        return $rows->map(fn (string $stat, string $type): string => "{$type}: {$stat}")->implode("\n") ?: 'No items.';
    }

    /**
     * Per-tag breakdown via relationship subqueries (withCount/withSum) — no
     * join, no raw SQL. Empty tags are filtered out to match the old behaviour.
     */
    private function groupedByTag(?ItemType $type, string $metric): string
    {
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

    /**
     * Overall total (no breakdown), optionally restricted to one type.
     */
    private function total(?ItemType $type, string $metric): string
    {
        $label = $this->label($type);

        if ($metric === 'value') {
            $total = Item::query()
                ->when($type, fn ($q) => $q->where('type', $type))
                ->whereNull('sold_date')
                ->sum('purchase_price');

            return "Total value of owned {$label}: ".$this->money($total);
        }

        $count = Item::query()
            ->when($type, fn ($q) => $q->where('type', $type))
            ->count();

        return "Total {$label}: {$count}";
    }

    /**
     * Plural label for the row: "items"/"rooms"/"containers", or "entries" when
     * no type filter is applied (i.e. counting everything).
     */
    private function label(?ItemType $type): string
    {
        return $type ? "{$type->value}s" : 'entries';
    }

    /**
     * Default to counting possessions when no type or grouping is requested —
     * that's what "how many items / total value" colloquially means. "all"
     * (sentinel string) is the opt-in for "include rooms and containers".
     */
    private function resolveType(mixed $raw, ?string $groupBy): ?ItemType
    {
        $value = is_string($raw) ? trim($raw) : '';

        if ($value === 'all') {
            return null;
        }

        if (($type = ItemType::tryFrom($value)) !== null) {
            return $type;
        }

        // No (or invalid) type given: default for overall totals; for groupings,
        // leave null so the breakdown covers every type.
        return $groupBy === null ? ItemType::Item : null;
    }

    /**
     * Format a numeric value with the household currency code so the model never guesses the symbol.
     */
    private function money(int|float|string $value): string
    {
        return $value.' '.config('stockroom.currency.code', 'USD');
    }
}
