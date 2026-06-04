<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight item shape for list endpoints. Expects `primaryImage` and
 * `homeAssistantLink` to be eager-loaded by the caller (safe under
 * Model::shouldBeStrict()).
 *
 * @mixin Item
 */
class ItemSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'parent_id' => $this->parent_id,
            'location_path' => $this->locationPath(),
            'quantity' => $this->quantity,
            'thumb_url' => $this->whenLoaded('primaryImage', fn () => $this->primaryImage?->thumbUrl()),
            'has_ha_link' => $this->whenLoaded('homeAssistantLink', fn (): bool => $this->homeAssistantLink !== null, false),
        ];
    }
}
