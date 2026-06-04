<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreHomeAssistantLinkRequest;
use App\Http\Resources\Api\V1\HomeAssistantLinkResource;
use App\Http\Resources\Api\V1\ItemResource;
use App\Models\Item;
use App\Services\Items\HomeAssistantLinker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class HomeAssistantLinkController extends Controller
{
    public function __construct(private readonly HomeAssistantLinker $linker) {}

    /**
     * Every item that currently has a Home Assistant link, each with its
     * embedded link — one call instead of N+1 (list + per-item show). Built for
     * the integration's Repair feature. Filter by `instance_id` so one HA
     * instance fetches only its own links. Returns the same ItemResource as
     * `items/{item}`, so the item + link shapes are identical.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $instanceId = $request->query('instance_id');

        $items = Item::query()
            ->whereHas('homeAssistantLink', function ($query) use ($instanceId): void {
                if ($instanceId !== null && $instanceId !== '') {
                    $query->where('instance_id', $instanceId);
                }
            })
            ->with(['homeAssistantLink', 'primaryImage'])
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 50), 100))
            ->withQueryString();

        return ItemResource::collection($items);
    }

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
