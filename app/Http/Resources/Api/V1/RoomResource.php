<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A room (top-level location). HA maps its areas onto these. `children` is
 * counted by the caller via withCount('children').
 *
 * @mixin Item
 */
class RoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'parent_id' => $this->parent_id,
            'location_path' => $this->locationPath(),
            'children_count' => $this->whenCounted('children'),
        ];
    }
}
