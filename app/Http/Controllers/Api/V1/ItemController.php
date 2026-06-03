<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreItemRequest;
use App\Http\Requests\Api\V1\UpdateItemRequest;
use App\Http\Resources\Api\V1\ItemResource;
use App\Http\Resources\Api\V1\ItemSummaryResource;
use App\Models\Item;
use App\Services\Items\ItemWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
    public function __construct(private readonly ItemWriter $writer) {}

    /** Relations loaded for the full ItemResource. */
    private const DETAIL_RELATIONS = ['tags', 'primaryImage', 'homeAssistantLink', 'customFieldValues.field'];

    /**
     * Paginated item list with the filters the Home Assistant integration
     * needs: by room (whole subtree), direct parent, tag, type, and whether
     * the item is linked to an HA entity.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $type = in_array($request->query('type'), array_column(ItemType::cases(), 'value'), true)
            ? $request->query('type')
            : null;

        $items = Item::query()
            ->with(['primaryImage', 'homeAssistantLink'])
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when(
                $request->filled('parent'),
                fn ($q) => $q->where('parent_id', $request->integer('parent')),
            )
            ->when(
                $request->filled('room'),
                fn ($q) => $q->whereIn('id', $this->roomDescendantIds($request->integer('room'))),
            )
            ->when(
                $request->filled('tag'),
                fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($request->integer('tag'))),
            )
            ->when(
                $request->has('has_ha_link'),
                fn ($q) => $request->boolean('has_ha_link')
                    ? $q->whereHas('homeAssistantLink')
                    : $q->whereDoesntHave('homeAssistantLink'),
            )
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 50), 100))
            ->withQueryString();

        return ItemSummaryResource::collection($items);
    }

    public function show(Item $item): ItemResource
    {
        return new ItemResource($item->load(self::DETAIL_RELATIONS));
    }

    /**
     * Create an item (e.g. Home Assistant auto-creating one for a device).
     * Goes through ItemWriter so it shares the web UI's create path —
     * detail-field normalisation, tag sync, search indexing.
     */
    public function store(StoreItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        unset($data['tags']);

        $item = $this->writer->create($data, $tagIds);

        return (new ItemResource($item->load(self::DETAIL_RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Partial update. Tags are only touched when the `tags` key is present
     * (PATCH semantics) — absent leaves existing tags alone.
     */
    public function update(UpdateItemRequest $request, Item $item): ItemResource
    {
        $data = $request->validated();
        $tagIds = array_key_exists('tags', $data) ? $data['tags'] : null;
        unset($data['tags']);

        $this->writer->update($item, $data, $tagIds);

        return new ItemResource($item->load(self::DETAIL_RELATIONS));
    }

    /**
     * IDs of everything beneath a room. Returns an empty set for a missing id
     * so the filter simply matches nothing rather than erroring.
     *
     * @return list<int>
     */
    private function roomDescendantIds(int $roomId): array
    {
        $room = Item::query()->find($roomId);

        return $room?->descendantIds() ?? [];
    }
}
