<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $items = Item::search($query)
            ->query(fn ($builder) => $builder->with('primaryImage'))
            ->take(20)
            ->get();

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
