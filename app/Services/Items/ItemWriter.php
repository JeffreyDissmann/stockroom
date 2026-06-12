<?php

declare(strict_types=1);

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Models\Item;

/**
 * The canonical create/update/move/delete logic for items, shared by the HTTP
 * controller and the AI assistant's write tools so both go through one path
 * (detail-field normalisation, tag sync, search re-indexing). Custom fields and
 * image handling stay in the controller — the assistant doesn't touch those.
 */
class ItemWriter
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $tagIds
     */
    public function create(array $data, array $tagIds = []): Item
    {
        $item = Item::create($this->normalise($data));
        $item->tags()->sync($tagIds);
        $item->searchable();

        return $item;
    }

    /**
     * Update an item. Pass $tagIds to replace its tags, or null to leave tags
     * untouched (partial edits, e.g. from the assistant).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $tagIds
     */
    public function update(Item $item, array $data, ?array $tagIds = null): Item
    {
        $item->update($this->normalise($data));
        $nameChanged = $item->wasChanged('name');

        if ($tagIds !== null) {
            $item->tags()->sync($tagIds);
        }

        $item->searchable();

        // A rename changes the location_path of everything inside this item.
        if ($nameChanged) {
            $item->reindexDescendants();
        }

        return $item;
    }

    /**
     * Normalise detail fields only when a type is present (a full write); partial
     * updates without a type are applied as-is so they don't reset other fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalise(array $data): array
    {
        return isset($data['type']) ? $this->normaliseDetailFields($data) : $data;
    }

    public function move(Item $item, ?int $parentId): Item
    {
        $item->update(['parent_id' => $parentId]);
        // The item re-indexes on save; its descendants' location_path shifted too.
        $item->reindexDescendants();

        return $item;
    }

    /**
     * Attach tags without removing existing ones (additive — "tag X as Y").
     *
     * @param  array<int, int>  $tagIds
     */
    public function assignTags(Item $item, array $tagIds): Item
    {
        $item->tags()->syncWithoutDetaching($tagIds);
        $item->searchable();

        return $item;
    }

    public function delete(Item $item): void
    {
        $item->delete();
    }

    /**
     * Default quantity and, for types without detail fields (rooms), blank the
     * acquisition/warranty/sale fields so they can't be persisted for a room.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normaliseDetailFields(array $data): array
    {
        $type = isset($data['type']) ? ItemType::from($data['type']) : null;

        if ($type !== null && ! $type->hasDetailFields()) {
            $data['quantity'] = 1;
            foreach (['purchased_from', 'purchase_date', 'purchase_price', 'manufacturer', 'model_number', 'serial_number', 'battery_type', 'warranty_expires', 'warranty_details', 'sold_to', 'sold_price', 'sold_date', 'sold_notes'] as $field) {
                $data[$field] = null;
            }
            $data['lifetime_warranty'] = false;

            return $data;
        }

        $data['quantity'] = $data['quantity'] ?? 1;

        return $data;
    }
}
