<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoomResource;
use App\Models\Item;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoomController extends Controller
{
    /**
     * Flat list of rooms (top-level locations) with a child count. Home
     * Assistant maps its areas onto these; the parent_id field lets a client
     * reconstruct any nesting.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $rooms = Item::query()
            ->where('type', ItemType::Room)
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return RoomResource::collection($rooms);
    }
}
