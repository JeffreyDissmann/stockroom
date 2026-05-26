<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Search\ItemEmbedder;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Scout\Builder;
use Throwable;

class SearchController extends Controller
{
    public function __construct(private readonly ItemEmbedder $embedder) {}

    /**
     * Run a Scout search, applying Meilisearch hybrid (semantic) options when
     * enabled and transparently falling back to keyword search if the embedder
     * isn't available — e.g. mid-rebuild or Ollama down — so search never
     * hard-fails. The $execute callback finalises the builder (->get(), ->keys()…).
     *
     * @template TReturn
     *
     * @param  callable(Builder): TReturn  $execute
     * @return TReturn
     */
    private function search(string $query, callable $execute): mixed
    {
        if (($hybrid = $this->hybrid($query)) !== null) {
            try {
                return $execute(Item::search($query, $hybrid));
            } catch (Throwable $e) {
                report($e); // keep visibility, then degrade to keyword search
            }
        }

        return $execute(Item::search($query));
    }

    /**
     * Meilisearch hybrid-search options, or null when semantic search is off,
     * the driver isn't Meilisearch (e.g. the "collection" driver in tests), or
     * the query can't be embedded. With a "userProvided" embedder Meilisearch
     * can't embed the query itself, so we embed it app-side and pass the vector.
     */
    private function hybrid(string $query): ?Closure
    {
        $embedder = config('scout.meilisearch.hybrid.embedder');

        if (config('scout.driver') !== 'meilisearch' || ! $embedder) {
            return null;
        }

        $vector = $this->embedder->embed($query);

        if ($vector === null) {
            return null;
        }

        $ratio = (float) config('scout.meilisearch.hybrid.semantic_ratio', 0.5);

        return fn ($index, string $q, array $options) => $index->search($q, $options + [
            'vector' => $vector,
            'hybrid' => ['embedder' => $embedder, 'semanticRatio' => $ratio],
        ]);
    }

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

        $items = $this->search($query, fn ($builder) => $builder
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
        $sort = $request->query('sort') === 'name' ? 'name' : 'relevance';

        // Relevance-ordered matching ids from Meilisearch (null = browse everything).
        $ids = $query !== '' ? $this->search($query, fn ($b) => $b->take(500)->keys()->all()) : null;

        $items = Item::query()
            ->with(['primaryImage', 'tags'])
            ->withCount('children')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->when($tagIds !== [], fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($tagIds)))
            ->when(
                $ids !== null && $ids !== [] && $sort === 'relevance',
                fn ($q) => $q->orderByRaw('array_position(?::int[], id)', ['{'.implode(',', $ids).'}']),
                fn ($q) => $q->orderBy('name'),
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
