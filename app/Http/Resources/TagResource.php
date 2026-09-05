<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A tag as the web UI renders it (chips, filters, item cards).
 *
 * Deliberately separate from Api\V1\TagResource even though the two currently
 * agree: the v1 namespace exists so the Home Assistant contract can stay frozen
 * while the UI changes. Sharing one class would make a card tweak an API break.
 *
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
        ];
    }
}
