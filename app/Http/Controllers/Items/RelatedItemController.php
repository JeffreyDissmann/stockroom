<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreRelatedItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Manages the symmetric many-to-many "related items" link on Item. Each
 * action ends with `back()` so the user stays on whichever item's Show
 * page they triggered it from.
 */
class RelatedItemController extends Controller
{
    public function store(StoreRelatedItemRequest $request, Item $item): RedirectResponse
    {
        $other = Item::findOrFail($request->integer('related_item_id'));

        try {
            $item->linkRelated($other);
        } catch (InvalidArgumentException $e) {
            // The model guards against self-linking; surface it as a form
            // validation error so the dialog can show it next to the picker.
            throw ValidationException::withMessages([
                'related_item_id' => $e->getMessage(),
            ]);
        }

        return back();
    }

    public function destroy(Item $item, Item $related): RedirectResponse
    {
        $item->unlinkRelated($related);

        return back();
    }
}
