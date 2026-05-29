<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreBoxRequest;
use App\Models\Item;
use App\Models\Setting;
use App\Services\Items\ItemWriter;
use Illuminate\Http\RedirectResponse;

/**
 * "Create a box for this item" — spawns a Container child of the source item
 * that represents its original packaging (the iPhone box, the Sonos box). The
 * box inherits identifying metadata from the item (serial / manufacturer /
 * description / quantity) so it can be tracked as the physical thing it is.
 */
class BoxController extends Controller
{
    public function __construct(private readonly ItemWriter $writer) {}

    public function store(StoreBoxRequest $request, Item $item): RedirectResponse
    {
        $box = $this->writer->create([
            // Default name prepends "BOX:" to the source item. Kept literal in
            // both locales per design decision in #9 so the slug stays stable
            // across users switching language.
            'name' => $request->input('name') ?: 'BOX: '.$item->name,
            // ItemWriter::normalise() calls ItemType::from() on `type`, so it
            // expects the backing string, not an enum instance.
            'type' => ItemType::Container->value,
            // The box is a CHILD of the item — it represents this exact unit's
            // packaging, not a generic bin. The item's detail page renders it
            // under "Contents" automatically via the existing children list.
            'parent_id' => $item->id,
            'serial_number' => $request->input('serial_number') ?? $item->serial_number,
            'manufacturer' => $request->input('manufacturer') ?? $item->manufacturer,
            'model_number' => $request->input('model_number') ?? $item->model_number,
            'description' => $request->input('description') ?? $item->description,
            'quantity' => $request->integer('quantity') ?: ($item->quantity ?? 1),
        ], $this->boxTagIds());

        // Redirect to the new box's show page with two pieces of one-shot UX:
        //  - flash `box_created_for` so a green confirmation banner renders
        //    above the page until the next navigation (HandleInertiaRequests
        //    exposes it as a prop).
        //  - `?focus=images` so the existing SearchImageDialog auto-opens —
        //    picking a photo is the natural next step once the metadata is
        //    saved, and the box obviously hasn't got one yet.
        return redirect()
            ->route('items.show', ['item' => $box->id, 'focus' => 'images'])
            ->with('box_created_for', $item->name);
    }

    /**
     * Tag ids to apply to a freshly-created box. Reads the
     * household-configured `box_tag_id` setting; returns an empty array
     * (no tagging) when the admin has cleared it.
     *
     * @return array<int, int>
     */
    private function boxTagIds(): array
    {
        $tagId = Setting::get('box_tag_id');

        return is_int($tagId) ? [$tagId] : [];
    }
}
