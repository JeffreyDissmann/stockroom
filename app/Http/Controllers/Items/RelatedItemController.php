<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StoreRelatedItemRequest;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;

/**
 * Manages the symmetric many-to-many "related items" link on Item. Each
 * action ends with `back()` so the user stays on whichever item's Show
 * page they triggered it from. Self-link prevention lives in the
 * StoreRelatedItemRequest validator; the model still guards as a backstop.
 */
class RelatedItemController extends Controller
{
    public function store(StoreRelatedItemRequest $request, Item $item): RedirectResponse
    {
        $item->linkRelated(Item::findOrFail($request->integer('related_item_id')));

        return back();
    }

    public function destroy(Item $item, Item $related): RedirectResponse
    {
        $item->unlinkRelated($related);

        return back();
    }
}
