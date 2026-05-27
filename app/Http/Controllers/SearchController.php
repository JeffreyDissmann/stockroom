<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Services\InventorySearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    public function __construct(private readonly InventorySearch $search) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        $query = trim((string) $request->query('q', ''));

        return $request->wantsJson()
            ? $this->suggestions($query)
            : $this->page($request, $query);
    }

    /**
     * Lightweight JSON for the command palette (top hits only).
     */
    private function suggestions(string $query): JsonResponse
    {
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

    /**
     * The full results page: fuzzy query (Meilisearch) refined by type/tag
     * filters + sort + pagination (database).
     */
    private function page(Request $request, string $query): Response
    {
        $type = in_array($request->query('type'), array_column(ItemType::cases(), 'value'), true)
            ? $request->query('type')
            : null;
        $tagIds = collect((array) $request->input('tags', []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $sort = in_array($request->query('sort'), ['name', 'added', 'edited'], true)
            ? $request->query('sort')
            : 'relevance';

        // Relevance-ordered matching ids from Meilisearch (null = browse everything).
        $ids = $query !== '' ? $this->search->search($query, fn ($b) => $b->take(500)->keys()->all()) : null;

        $items = Item::query()
            ->with(['primaryImage', 'tags'])
            ->withCount('children')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($tagIds !== [], fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($tagIds)))
            ->when(
                $ids !== null && $ids !== [] && $sort === 'relevance',
                fn ($q) => $q->orderByRaw('array_position(?::int[], id)', ['{'.implode(',', $ids).'}']),
                fn ($q) => match ($sort) {
                    'added' => $q->orderByDesc('created_at'),
                    'edited' => $q->orderByDesc('updated_at'),
                    default => $q->orderBy('name'),
                },
            )
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Item $item): array => $this->present($item));

        return Inertia::render('Search', [
            'query' => $query,
            'filters' => ['type' => $type, 'tags' => $tagIds, 'sort' => $sort],
            'items' => $items,
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'types' => collect(ItemType::cases())
                ->map(fn (ItemType $t): array => ['value' => $t->value, 'label' => $t->label()])
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Item $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'type' => [
                'value' => $item->type->value,
                'label' => $item->type->label(),
                'icon' => $item->type->icon(),
                'details' => $item->type->hasDetailFields(),
            ],
            'thumb_url' => $item->primaryImage?->thumbUrl(),
            'icon' => $item->icon,
            'children_count' => $item->children_count,
            'tags' => $item->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'color' => $tag->color,
            ])->values(),
        ];
    }
}
