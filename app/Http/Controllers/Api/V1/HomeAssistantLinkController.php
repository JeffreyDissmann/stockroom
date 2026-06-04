<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHomeAssistantLinkRequest;
use App\Http\Resources\Api\V1\HomeAssistantLinkResource;
use App\Models\Item;
use App\Services\Items\HomeAssistantLinker;
use Illuminate\Http\Response;

class HomeAssistantLinkController extends Controller
{
    public function __construct(private readonly HomeAssistantLinker $linker) {}

    /**
     * Set or replace the item's Home Assistant link. Idempotent (strictly 1:1
     * per item) and auto-assigns the "HomeAssistant" tag via the linker.
     */
    public function update(StoreHomeAssistantLinkRequest $request, Item $item): HomeAssistantLinkResource
    {
        return new HomeAssistantLinkResource($this->linker->link($item, $request->validated()));
    }

    public function destroy(Item $item): Response
    {
        $this->linker->unlink($item);

        return response()->noContent();
    }
}
