<?php

declare(strict_types=1);

namespace App\Services\Items;

use App\Enums\ItemType;
use App\Enums\SaleDisposition;
use App\Models\CustomField;
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
     * Settle what happened to a sold container's contents.
     *
     * Selling a container leaves its contents somewhere they cannot be reached:
     * browsing is a walk down parent_id, and a sold container is not listed, so
     * whatever is inside it can still be searched for but never navigated to.
     * There is no safe guess between the two cases — the tools went with the
     * toolbox, or the toolbox went and the tools stayed — and each is quietly
     * wrong for months if assumed. So the caller must say which.
     *
     * @return int Items affected, so the caller can say what just happened.
     */
    public function applySaleToContents(Item $item, SaleDisposition $disposition): int
    {
        if ($disposition === SaleDisposition::Kept) {
            // Same shape as deleting the container: the contents move up to
            // where it stood, rather than being orphaned at the top level.
            $children = $item->children()->get();

            foreach ($children as $child) {
                $this->move($child, $item->parent_id);
            }

            return $children->count();
        }

        // Sold with it: the whole subtree left the house. Already-sold
        // descendants keep their own date — that sale is a separate event.
        $ids = $item->descendantIds();

        if ($ids === []) {
            return 0;
        }

        $descendants = Item::query()->whereKey($ids)->owned()->get();

        foreach ($descendants as $descendant) {
            // No sale price: the container's price covers the lot, and copying
            // it down would count the same money once per item.
            $descendant->update(['sold_date' => $item->sold_date, 'sold_to' => $item->sold_to]);
        }

        $descendants->searchable();

        return $descendants->count();
    }

    /**
     * Upsert the submitted custom field values (keyed by definition id),
     * removing any that were cleared. Only user-editable definitions are
     * touched so import-managed system values are preserved.
     *
     * @param  array<int|string, mixed>  $values
     */
    public function syncCustomFields(Item $item, array $values): void
    {
        foreach (CustomField::query()->where('is_system', false)->get() as $field) {
            $stored = $field->type->serialize($values[$field->id] ?? null);

            if ($stored === null) {
                $item->customFieldValues()->where('custom_field_id', $field->id)->delete();

                continue;
            }

            $item->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }
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
