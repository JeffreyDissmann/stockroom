<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHomeAssistantLinkRequest;
use App\Http\Resources\Api\V1\HomeAssistantLinkResource;
use App\Models\Item;
use Illuminate\Http\Response;

class HomeAssistantLinkController extends Controller
{
    /**
     * Set or replace the item's Home Assistant link. Idempotent: the unique
     * item_id constraint plus updateOrCreate keeps it strictly 1:1, so calling
     * this twice updates the same row rather than creating a second.
     */
    public function update(StoreHomeAssistantLinkRequest $request, Item $item): HomeAssistantLinkResource
    {
        $link = $item->homeAssistantLink()->updateOrCreate(
            ['item_id' => $item->id],
            $request->validated(),
        );

        return new HomeAssistantLinkResource($link);
    }

    public function destroy(Item $item): Response
    {
        $item->homeAssistantLink()->delete();

        return response()->noContent();
    }
}
