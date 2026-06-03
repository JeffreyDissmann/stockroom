<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\HomeAssistantLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HomeAssistantLink
 */
class HomeAssistantLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ha_entity_id' => $this->ha_entity_id,
            'ha_device_id' => $this->ha_device_id,
            'friendly_name' => $this->friendly_name,
            'url' => $this->url,
            'instance_id' => $this->instance_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
