<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Http\Requests\Item\MoveItemRequest;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
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
            'customFields' => $this->customFieldDefinitions(),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        $imageFiles = $request->file('images', []);
        unset($data['tags'], $data['images'], $data['custom_fields']);
        $data = $this->normaliseDetailFields($data);

        $item = Item::create($data);
        $item->tags()->sync($tagIds);
        $this->syncCustomFields($item, $customFields);

        foreach ($imageFiles as $file) {
            $this->imageProcessor->store($item, $file);
        }

        return to_route('items.show', $item);
    }

    public function show(Item $item): Response
    {
        $item->load(['tags', 'images', 'customFieldValues.field']);
        $children = $item->children()->withCount('children')->with(['tags', 'primaryImage'])->get();

        return Inertia::render('items/Show', [
            'item' => $this->presentItem($item, withTags: true, withImages: true, withDetails: true),
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
        $item->load(['tags', 'images', 'customFieldValues.field']);

        return Inertia::render('items/Edit', [
            'item' => $this->presentItem($item, withTags: true, withImages: true, withDetails: true),
            'tags' => Tag::query()->orderBy('name')->get(),
            'types' => $this->typeOptions(),
            'customFields' => $this->customFieldDefinitions(),
        ]);
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        unset($data['tags'], $data['custom_fields']);
        $data = $this->normaliseDetailFields($data);

        $item->update($data);
        $item->tags()->sync($tagIds);
        $this->syncCustomFields($item, $customFields);

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
     * @return array<int, array{value: string, label: string, icon: string, details: bool}>
     */
    private function typeOptions(): array
    {
        return collect(ItemType::cases())
            ->map(fn (ItemType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'icon' => $t->icon(),
                'details' => $t->hasDetailFields(),
            ])
            ->values()
            ->all();
    }

    /**
     * Default quantity and, for types that don't carry detail fields (rooms),
     * blank out the acquisition/warranty/sale fields so they can't be persisted
     * via a crafted request even though the form hides them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normaliseDetailFields(array $data): array
    {
        $type = isset($data['type']) ? ItemType::from($data['type']) : null;

        if ($type !== null && ! $type->hasDetailFields()) {
            $data['quantity'] = 1;
            foreach (['purchased_from', 'purchase_date', 'purchase_price', 'manufacturer', 'model_number', 'serial_number', 'warranty_expires', 'warranty_details', 'sold_to', 'sold_price', 'sold_date', 'sold_notes'] as $field) {
                $data[$field] = null;
            }
            $data['lifetime_warranty'] = false;

            return $data;
        }

        $data['quantity'] = $data['quantity'] ?? 1;

        return $data;
    }

    /**
     * The user-editable custom field definitions shown on the item form
     * (system fields are import-managed and never edited by hand).
     *
     * @return array<int, array{id: int, key: string, name: string, type: string}>
     */
    private function customFieldDefinitions(): array
    {
        return CustomField::query()
            ->where('is_system', false)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'name' => $field->name,
                'type' => $field->type->value,
            ])
            ->all();
    }

    /**
     * Upsert the submitted custom field values (keyed by definition id),
     * removing any that were cleared. Only user-editable definitions are
     * touched so import-managed system values are preserved.
     *
     * @param  array<int|string, mixed>  $values
     */
    private function syncCustomFields(Item $item, array $values): void
    {
        foreach (CustomField::query()->where('is_system', false)->get() as $field) {
            $stored = $field->type->serialize($values[$field->id] ?? null);

            if ($stored === null) {
                $item->customFieldValues()->where('custom_field_id', $field->id)->delete();

                continue;
            }

            $item->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }
    }

    private function presentItem(Item $item, bool $withChildrenCount = false, bool $withTags = false, bool $withImages = false, bool $withDetails = false): array
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
                'details' => $item->type->hasDetailFields(),
            ],
            'thumb_url' => $item->relationLoaded('primaryImage') && $item->primaryImage
                ? $item->primaryImage->thumbUrl()
                : null,
        ];

        if ($withChildrenCount) {
            $payload['children_count'] = $item->children_count ?? $item->children()->count();
        }

        if ($withDetails) {
            $payload['quantity'] = $item->quantity;
            $payload['purchased_from'] = $item->purchased_from;
            $payload['purchase_date'] = $item->purchase_date?->toDateString();
            $payload['purchase_price'] = $item->purchase_price;
            $payload['manufacturer'] = $item->manufacturer;
            $payload['model_number'] = $item->model_number;
            $payload['serial_number'] = $item->serial_number;
            $payload['lifetime_warranty'] = $item->lifetime_warranty;
            $payload['warranty_expires'] = $item->warranty_expires?->toDateString();
            $payload['warranty_details'] = $item->warranty_details;
            $payload['sold_to'] = $item->sold_to;
            $payload['sold_price'] = $item->sold_price;
            $payload['sold_date'] = $item->sold_date?->toDateString();
            $payload['sold_notes'] = $item->sold_notes;
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

        if ($withDetails && $item->relationLoaded('customFieldValues')) {
            $payload['custom_fields'] = $item->customFieldValues
                ->filter(fn (CustomFieldValue $v) => $v->field !== null && ! $v->field->is_system)
                ->map(fn (CustomFieldValue $v) => [
                    'custom_field_id' => $v->custom_field_id,
                    'key' => $v->field->key,
                    'name' => $v->field->name,
                    'type' => $v->field->type->value,
                    'value' => $v->field->type->cast($v->value),
                ])
                ->values();
        }

        return $payload;
    }
}
