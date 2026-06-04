<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Tag;
use App\Services\InventoryStatistics;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    public function __construct(private readonly InventoryStatistics $stats) {}

    /**
     * Inventory roll-up for Home Assistant statistics sensors. Not backed by
     * a single model, so this returns a plain JSON structure rather than an
     * Eloquent resource. The queries are shared with the dashboard via
     * InventoryStatistics; the API returns the full (unlimited) breakdowns.
     */
    public function __invoke(): JsonResponse
    {
        $byType = $this->stats->countsByType();

        $byTag = $this->stats->tagsWithItemCounts()
            ->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
                'items_count' => $tag->items_count,
            ]);

        $byRoom = $this->stats->roomsWithChildCounts()
            ->map(fn (Item $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'icon' => $room->icon,
                'children_count' => $room->children_count,
            ]);

        return response()->json([
            'total' => $byType->sum(),
            'value' => $this->stats->ownedValue(),
            'by_type' => $byType,
            'by_tag' => $byTag,
            'by_room' => $byRoom,
        ]);
    }
}
