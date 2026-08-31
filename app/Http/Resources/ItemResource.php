<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use App\Models\ItemImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item as every card surface renders it — the inventory list, a container's
 * contents, related items, search results and the dashboard's recent strip.
 *
 * This replaces a boolean-flag serialiser (`presentItem($item, withTags: true,
 * withThumbs: true, …)`) that had been copied into three controllers and drifted
 * in ways users could see: search results shipped no `is_sold`, so the archive
 * read as though you still owned everything in it.
 *
 * Which optional fields appear is decided by what the caller eager-loaded, not
 * by argument flags — so a page gets exactly what it asked the database for.
 * Pages needing the acquisition/warranty/sale block use ItemDetailResource.
 *
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    /**
     * Set only by callers that resolved ancestor paths in a batch (search).
     * Left null elsewhere so no card triggers a per-row ancestor walk.
     */
    private ?string $locationPath = null;

    /**
     * Attach the pre-resolved "Garage / Toolbox" path.
     *
     * @see Item::locationPathsFor() for the batched lookup that produces it
     */
    public function withLocationPath(?string $path): static
    {
        $this->locationPath = $path;

        return $this;
    }

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
            // Sold items are hidden by default, so wherever one does surface —
            // the archive filter, related items, a direct link — it has to say
            // plainly that it is not yours any more.
            'is_sold' => $this->sold_date !== null,
            'type' => $this->type->descriptor(),
            'icon' => $this->icon,
            'thumb_url' => $this->thumbnailUrl(),
            'children_count' => $this->whenCounted('children'),
            // resolve() on the nested collection, not just TagResource::collection():
            // the outer resource is resolved by hand for Inertia, and that does
            // not walk into nested resources. Left unresolved, this serialises
            // as {"data": [...]} instead of a plain array — which silently
            // rendered no tags on the cards and bailed Vue out of the whole item
            // page, where the list is iterated directly.
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve($request)),
            // Lightweight thumbnail list (primary first) for the card carousel.
            'image_thumbs' => $this->when(
                $this->resource->relationLoaded('images'),
                fn () => $this->images
                    ->sortByDesc('is_primary')
                    ->map(fn (ItemImage $image): string => $image->thumbUrl())
                    ->values(),
            ),
            'location_path' => $this->when($this->locationPath !== null, fn () => $this->locationPath),
            // Only the dashboard's "recently added" strip loads this, to link
            // each new thing to the room it landed in. Null for a top-level item.
            'parent' => $this->whenLoaded(
                'parent',
                fn () => $this->parent === null ? null : ItemResource::make($this->parent)->resolve(),
            ),
        ];
    }
}
