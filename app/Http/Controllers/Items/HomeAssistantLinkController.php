<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\Items\HomeAssistantLinker;
use Illuminate\Http\RedirectResponse;

/**
 * Removes an item's Home Assistant link from the Stockroom side (the Edit
 * page). The link is created by the Home Assistant integration via the v1 API;
 * unlinking here goes through the same linker so the "HomeAssistant" tag is
 * removed too. If the device still points at this item, Home Assistant
 * re-links on its next sync.
 */
class HomeAssistantLinkController extends Controller
{
    public function __construct(private readonly HomeAssistantLinker $linker) {}

    public function destroy(Item $item): RedirectResponse
    {
        $this->linker->unlink($item);

        return back();
    }
}
