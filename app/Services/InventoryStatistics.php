<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the inventory roll-up queries shared by the
 * dashboard (top-N, web shape) and the v1 API statistics endpoint (full
 * list, HA shape). Each method returns raw queried data; callers map it to
 * their own presentation.
 */
class InventoryStatistics
{
    /**
     * Item counts keyed by ItemType value, zero-filled for every case. A few
     * indexed COUNTs over the small, fixed enum — no raw grouped query.
     *
     * @return Collection<string, int>
     */
    public function countsByType(): Collection
    {
        return collect(ItemType::cases())
            ->mapWithKeys(fn (ItemType $type): array => [
                $type->value => Item::query()->where('type', $type)->count(),
            ]);
    }

    /**
     * Estimated value of what's currently owned — sold items excluded.
     */
    public function ownedValue(): float
    {
        return (float) Item::query()->whereNull('sold_date')->sum('purchase_price');
    }

    /**
     * Tags with their item counts, fullest first. Pass a limit for the
     * dashboard's top-N strip; omit it for the full API list.
     *
     * @return Collection<int, Tag>
     */
    public function tagsWithItemCounts(?int $limit = null): Collection
    {
        return Tag::query()
            ->withCount('items')
            ->orderByDesc('items_count')
            ->orderBy('name')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get(['id', 'name', 'slug', 'color']);
    }

    /**
     * Rooms with their direct-child counts, fullest first.
     *
     * @return Collection<int, Item>
     */
    public function roomsWithChildCounts(?int $limit = null): Collection
    {
        return Item::query()
            ->where('type', ItemType::Room)
            ->withCount('children')
            ->orderByDesc('children_count')
            ->orderBy('name')
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->get(['id', 'name', 'icon']);
    }
}
