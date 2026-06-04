<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;

/**
 * Removes an item's Home Assistant link from the Stockroom side. The link is
 * created by the Home Assistant integration via the v1 API; unlinking here is
 * a pure local delete (it's 1:1, so there's at most one row). If the device
 * still points at this item, Home Assistant re-links on its next sync.
 */
class HomeAssistantLinkController extends Controller
{
    public function destroy(Item $item): RedirectResponse
    {
        $item->homeAssistantLink()->delete();

        return back();
    }
}
