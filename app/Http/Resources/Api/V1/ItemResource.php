<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CustomFieldValue;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full item detail. Tags, customFieldValues.field, primaryImage and
 * homeAssistantLink should be eager-loaded by the caller.
 *
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'icon' => $this->icon,
            'location_path' => $this->locationPath(),
            'quantity' => $this->quantity,
            'purchased_from' => $this->purchased_from,
            'purchase_date' => $this->purchase_date?->toDateString(),
            'purchase_price' => $this->purchase_price,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'serial_number' => $this->serial_number,
            'lifetime_warranty' => $this->lifetime_warranty,
            'warranty_expires' => $this->warranty_expires?->toDateString(),
            'warranty_details' => $this->warranty_details,
            'sold_to' => $this->sold_to,
            'sold_price' => $this->sold_price,
            'sold_date' => $this->sold_date?->toDateString(),
            'sold_notes' => $this->sold_notes,
            'thumb_url' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage?->thumbUrl()),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'custom_fields' => $this->when(
                $this->relationLoaded('customFieldValues'),
                fn () => $this->customFieldValues
                    ->filter(fn (CustomFieldValue $v): bool => $v->field !== null && ! $v->field->is_system)
                    ->map(fn (CustomFieldValue $v): array => [
                        'custom_field_id' => $v->custom_field_id,
                        'key' => $v->field->key,
                        'name' => $v->field->name,
                        'type' => $v->field->type->value,
                        'value' => $v->field->type->cast($v->value),
                    ])
                    ->values(),
            ),
            'home_assistant_link' => $this->whenLoaded(
                'homeAssistantLink',
                fn () => $this->homeAssistantLink !== null
                    ? new HomeAssistantLinkResource($this->homeAssistantLink)
                    : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
