<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\InventorySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly InventorySearch $search) {}

    /**
     * "Where is X?" — reuses the same hybrid (keyword + semantic) search as
     * the web command palette, returning the top hits with their location
     * path. Same shape as the SPA's suggestions endpoint.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $items = $this->search->search($query, fn ($builder) => $builder
            ->query(fn ($q) => $q->with('primaryImage'))
            ->take(20)
            ->get());

        return response()->json([
            'results' => $items->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => ['value' => $item->type->value, 'label' => $item->type->label()],
                'path' => $item->locationPath(),
                'thumb_url' => $item->primaryImage?->thumbUrl(),
            ])->all(),
        ]);
    }
}
