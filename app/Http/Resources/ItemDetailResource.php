<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomFieldValue;
use App\Models\Item;
use Illuminate\Http\Request;

/**
 * The item's own page (Show / Edit): everything a card gets, plus the
 * acquisition, warranty and sale block, the full image records and the filled
 * custom field values.
 *
 * Split from ItemResource rather than gated by a flag so the list pages cannot
 * accidentally ship a sale price for every row, and so the TypeScript side can
 * tell a card's payload from a detail payload instead of marking all 25 fields
 * optional.
 *
 * @mixin Item
 */
class ItemDetailResource extends ItemResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'quantity' => $this->quantity,
            'purchased_from' => $this->purchased_from,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_price' => $this->purchase_price,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'serial_number' => $this->serial_number,
            'battery_type' => $this->battery_type,
            'lifetime_warranty' => $this->lifetime_warranty,
            'warranty_expires' => $this->warranty_expires?->toDateString(),
            'warranty_details' => $this->warranty_details,
            'sold_to' => $this->sold_to,
            'sold_price' => $this->sold_price,
            'sold_date' => $this->sold_date?->toDateString(),
            'sold_notes' => $this->sold_notes,
            'images' => $this->whenLoaded('images', fn () => ItemImageResource::collection($this->images)->resolve($request)),
            // System fields are import-managed and never edited by hand, so the
            // form never sees them. A value whose definition has been deleted is
            // dropped rather than rendered as a nameless row.
            'custom_fields' => $this->when(
                $this->resource->relationLoaded('customFieldValues'),
                fn () => $this->customFieldValues
                    ->filter(fn (CustomFieldValue $value): bool => $value->field !== null && ! $value->field->is_system)
                    ->map(fn (CustomFieldValue $value): array => [
                        'custom_field_id' => $value->custom_field_id,
                        'key' => $value->field->key,
                        'name' => $value->field->name,
                        'type' => $value->field->type->value,
                        'value' => $value->field->type->cast($value->value),
                    ])
                    ->values(),
            ),
        ];
    }
}
