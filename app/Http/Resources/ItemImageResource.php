<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ItemImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full image record the gallery and the image manager need. Card surfaces
 * take the lighter `image_thumbs` list on ItemResource instead.
 *
 * @mixin ItemImage
 */
class ItemImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thumb_url' => $this->thumbUrl(),
            'large_url' => $this->largeUrl(),
            'original_url' => $this->originalUrl(),
            'is_primary' => $this->is_primary,
            'sort_order' => $this->sort_order,
        ];
    }
}
