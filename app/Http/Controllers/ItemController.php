<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Http\Requests\Item\MoveItemRequest;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $parentId = $request->integer('parent') ?: null;
        $parent = $parentId ? Item::findOrFail($parentId) : null;

        $items = Item::query()
            ->where('parent_id', $parentId)
            ->withCount('children')
            ->with('tags')
            ->orderBy('name')
            ->get();

        return Inertia::render('items/Index', [
            'parent' => $parent ? $this->presentItem($parent) : null,
            'breadcrumb' => $parent
                ? $parent->ancestors()->push($parent)->map(fn (Item $i) => $this->presentItem($i))->values()
                : [],
            'items' => $items->map(fn (Item $i) => $this->presentItem($i, withChildrenCount: true))->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $parentId = $request->integer('parent') ?: null;
        $parent = $parentId ? Item::findOrFail($parentId) : null;

        return Inertia::render('items/Create', [
            'parent' => $parent ? $this->presentItem($parent) : null,
            'items' => Item::query()->orderBy('name')->get()->map(fn (Item $i) => $this->presentItem($i))->values(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'types' => $this->typeOptions(),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        unset($data['tags']);

        $item = Item::create($data);
        $item->tags()->sync($tagIds);

        return to_route('items.show', $item);
    }

    public function show(Item $item): Response
    {
        $item->load('tags');
        $children = $item->children()->withCount('children')->with('tags')->get();

        return Inertia::render('items/Show', [
            'item' => $this->presentItem($item, withTags: true),
            'breadcrumb' => $item->ancestors()->map(fn (Item $i) => $this->presentItem($i))->values(),
            'children' => $children->map(fn (Item $i) => $this->presentItem($i, withChildrenCount: true, withTags: true))->values(),
        ]);
    }

    public function edit(Item $item): Response
    {
        $item->load('tags');

        return Inertia::render('items/Edit', [
            'item' => $this->presentItem($item, withTags: true),
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

    private function presentItem(Item $item, bool $withChildrenCount = false, bool $withTags = false): array
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

        return $payload;
    }
}
