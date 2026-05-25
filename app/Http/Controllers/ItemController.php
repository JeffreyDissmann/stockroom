<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Http\Requests\Item\MoveItemRequest;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\Tag;
use App\Services\ItemImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function __construct(private readonly ItemImageProcessor $imageProcessor) {}

    public function index(Request $request): Response
    {
        $parentId = $request->integer('parent') ?: null;
        $parent = $parentId ? Item::findOrFail($parentId) : null;

        $items = Item::query()
            ->where('parent_id', $parentId)
            ->withCount('children')
            ->with(['tags', 'primaryImage'])
            ->orderBy('name')
            ->get();

        $parentForView = $parent;
        if ($parentForView) {
            $parentForView->load('primaryImage');
        }

        return Inertia::render('items/Index', [
            'parent' => $parentForView ? $this->presentItem($parentForView) : null,
            'breadcrumb' => $parent
                ? $parent->ancestors()->push($parent)->map(fn (Item $i) => $this->presentItem($i))->values()
                : [],
            'items' => $items->map(fn (Item $i) => $this->presentItem($i, withChildrenCount: true))->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $parentId = $request->integer('parent') ?: null;
        $parent = $parentId ? Item::findOrFail($parentId)->load('primaryImage') : null;

        return Inertia::render('items/Create', [
            'parent' => $parent ? $this->presentItem($parent) : null,
            'items' => Item::query()->with('primaryImage')->orderBy('name')->get()->map(fn (Item $i) => $this->presentItem($i))->values(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $imageFiles = $request->file('images', []);
        unset($data['tags'], $data['images']);

        $item = Item::create($data);
        $item->tags()->sync($tagIds);

        foreach ($imageFiles as $file) {
            $this->imageProcessor->store($item, $file);
        }

        return to_route('items.show', $item);
    }

    public function show(Item $item): Response
    {
        $item->load(['tags', 'images']);
        $children = $item->children()->withCount('children')->with(['tags', 'primaryImage'])->get();

        return Inertia::render('items/Show', [
            'item' => $this->presentItem($item, withTags: true, withImages: true),
            'breadcrumb' => $item->ancestors()->map(fn (Item $i) => $this->presentItem($i))->values(),
            'children' => $children->map(fn (Item $i) => $this->presentItem($i, withChildrenCount: true, withTags: true))->values(),
            'moveTargets' => $this->moveTargets($item),
        ]);
    }

    /**
     * Eligible new parents for an item: every other item except the item itself
     * and its descendants (mirrors MoveItemRequest's cycle guard). Each carries a
     * breadcrumb path so same-named items are distinguishable in the picker.
     *
     * @return array<int, array{id: int, name: string, path: string, type: array{value: string, label: string}}>
     */
    private function moveTargets(Item $item): array
    {
        $all = Item::query()->orderBy('name')->get(['id', 'parent_id', 'type', 'name']);
        $byId = $all->keyBy('id');
        $byParent = $all->groupBy('parent_id');

        $excluded = collect([$item->id]);
        $stack = [$item->id];
        while ($stack !== []) {
            foreach ($byParent->get(array_pop($stack), collect()) as $child) {
                $excluded->push($child->id);
                $stack[] = $child->id;
            }
        }

        return $all
            ->reject(fn (Item $candidate) => $excluded->contains($candidate->id))
            ->map(function (Item $candidate) use ($byId) {
                $names = collect([$candidate->name]);
                $cursor = $candidate->parent_id;
                while ($cursor !== null && $byId->has($cursor)) {
                    $names->prepend($byId->get($cursor)->name);
                    $cursor = $byId->get($cursor)->parent_id;
                }

                return [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'path' => $names->implode(' / '),
                    'type' => [
                        'value' => $candidate->type->value,
                        'label' => $candidate->type->label(),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    public function edit(Item $item): Response
    {
        $item->load(['tags', 'images']);

        return Inertia::render('items/Edit', [
            'item' => $this->presentItem($item, withTags: true, withImages: true),
            'tags' => Tag::query()->orderBy('name')->get(),
            'types' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        unset($data['tags']);

        $item->update($data);
        $item->tags()->sync($tagIds);

        return to_route('items.show', $item);
    }

    public function destroy(Item $item): RedirectResponse
    {
        $parentId = $item->parent_id;
        $item->delete();

        return $parentId
            ? to_route('items.show', $parentId)
            : to_route('items.index');
    }

    public function move(MoveItemRequest $request, Item $item): RedirectResponse
    {
        $item->update(['parent_id' => $request->input('parent_id')]);

        return back();
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    private function typeOptions(): array
    {
        return collect(ItemType::cases())
            ->map(fn (ItemType $t) => ['value' => $t->value, 'label' => $t->label(), 'icon' => $t->icon()])
            ->values()
            ->all();
    }

    private function presentItem(Item $item, bool $withChildrenCount = false, bool $withTags = false, bool $withImages = false): array
    {
        $payload = [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'parent_id' => $item->parent_id,
            'type' => [
                'value' => $item->type->value,
                'label' => $item->type->label(),
                'icon' => $item->type->icon(),
            ],
            'thumb_url' => $item->relationLoaded('primaryImage') && $item->primaryImage
                ? $item->primaryImage->thumbUrl()
                : null,
        ];

        if ($withChildrenCount) {
            $payload['children_count'] = $item->children_count ?? $item->children()->count();
        }

        if ($withTags) {
            $payload['tags'] = $item->tags->map(fn (Tag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'color' => $t->color,
            ])->values();
        }

        if ($withImages) {
            $payload['images'] = $item->images->map(fn (ItemImage $img) => [
                'id' => $img->id,
                'thumb_url' => $img->thumbUrl(),
                'large_url' => $img->largeUrl(),
                'original_url' => $img->originalUrl(),
                'is_primary' => $img->is_primary,
                'sort_order' => $img->sort_order,
            ])->values();
        }

        return $payload;
    }
}
