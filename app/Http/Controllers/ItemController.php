<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\BatteryType;
use App\Enums\ItemType;
use App\Enums\MaintenanceScheduleType;
use App\Enums\SaleDisposition;
use App\Http\Requests\Item\MoveItemRequest;
use App\Http\Requests\Item\StoreItemRequest;
use App\Http\Requests\Item\UpdateItemRequest;
use App\Http\Resources\ItemDetailResource;
use App\Http\Resources\ItemResource;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\PaperlessLink;
use App\Models\Setting;
use App\Models\Tag;
use App\Services\ActivityPresenter;
use App\Services\Battery\BatteryPresenter;
use App\Services\ItemImageProcessor;
use App\Services\Items\ItemWriter;
use App\Services\Maintenance\MaintenancePresenter;
use App\Services\Proposals\ProposalPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemImageProcessor $imageProcessor,
        private readonly ActivityPresenter $activityPresenter,
        private readonly ItemWriter $writer,
        private readonly MaintenancePresenter $maintenancePresenter,
        private readonly BatteryPresenter $batteryPresenter,
        private readonly ProposalPresenter $proposals,
    ) {}

    public function index(Request $request): Response
    {
        $parentId = $request->integer('parent') ?: null;
        $parent = $parentId ? Item::findOrFail($parentId) : null;

        // Sold items are an archive, not inventory. `?sold=1` browses it —
        // the contents of a room as it was, rather than as it is.
        $includeSold = $request->boolean('sold');

        $items = Item::query()
            ->where('parent_id', $parentId)
            ->when(! $includeSold, fn ($q) => $q->owned())
            ->withCount(['children' => fn ($q) => $q->owned()])
            ->with(['tags', 'images'])
            ->orderBy('name')
            ->get();

        $parentForView = $parent;
        if ($parentForView) {
            $parentForView->load('primaryImage');
        }

        return Inertia::render('items/Index', [
            'parent' => $parentForView ? ItemResource::make($parentForView)->resolve() : null,
            'breadcrumb' => $parent
                ? ItemResource::collection($parent->ancestors()->push($parent))->resolve()
                : [],
            'items' => ItemResource::collection($items)->resolve(),
            // For the bulk-tag dialog. Sent unconditionally rather than
            // lazy-loaded — tags are small (typically <100 rows) and the
            // round-trip when entering Select mode would feel sluggish.
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
            'includeSold' => $includeSold,
            // Only offer the archive where one exists, so the toggle does not
            // sit on every empty shelf in the house.
            'soldCount' => Item::query()->sold()->where('parent_id', $parentId)->count(),
        ]);
    }

    public function create(Request $request): Response
    {
        $parentId = $request->integer('parent') ?: null;
        $parent = $parentId ? Item::findOrFail($parentId)->load('primaryImage') : null;

        return Inertia::render('items/Create', [
            'parent' => $parent ? ItemResource::make($parent)->resolve() : null,
            // Sold containers are not somewhere you can put anything: the new
            // item would vanish from the tree the moment it was saved.
            'items' => ItemResource::collection(Item::query()->owned()->with('primaryImage')->orderBy('name')->get())->resolve(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'types' => $this->typeOptions(),
            'customFields' => $this->customFieldDefinitions(),
            'batteryTypes' => BatteryType::values(),
        ]);
    }

    public function store(StoreItemRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        $imageFiles = $request->file('images', []);
        unset($data['tags'], $data['images'], $data['custom_fields']);

        $item = $this->writer->create($data, $tagIds);
        $this->writer->syncCustomFields($item, $customFields);

        foreach ($imageFiles as $file) {
            $this->imageProcessor->store($item, $file);
        }

        // Re-index now that custom fields + images are attached.
        $item->searchable();

        return to_route('items.show', $item);
    }

    public function show(Request $request, Item $item): Response
    {
        $item->load(['tags', 'images', 'customFieldValues.field', 'paperlessLinks', 'homeAssistantLink']);

        // Contents is the other way to browse the tree, and it needs the same
        // rule as the inventory list: a room holds what you still own.
        $includeSold = $request->boolean('sold');

        $children = $item->children()
            ->when(! $includeSold, fn ($query) => $query->owned())
            ->withCount(['children' => fn ($query) => $query->owned()])
            ->with(['tags', 'images'])
            ->get();
        // Related items survive moves around the tree, so they're a separate
        // edge from `children`. Eager-load enough for the same card layout
        // the Contents section uses.
        $relatedItems = $item->relatedItems()->withCount('children')->with(['tags', 'images'])->get();

        $activities = Activity::query()
            ->whereMorphedTo('subject', $item)
            ->with(['causer', 'subject'])
            ->latest()
            ->latest('id')
            ->limit(25)
            ->get()
            ->map(fn (Activity $activity): array => $this->activityPresenter->present($activity))
            ->all();

        return Inertia::render('items/Show', [
            'item' => ItemDetailResource::make($item)->resolve(),
            'breadcrumb' => ItemResource::collection($item->ancestors())->resolve(),
            'children' => ItemResource::collection($children)->resolve(),
            'includeSold' => $includeSold,
            'soldCount' => $item->children()->sold()->count(),
            'relatedItems' => ItemResource::collection($relatedItems)->resolve(),
            // Paperless-ngx documents the user linked this item to (#7).
            // Each entry is a click-through to the Paperless UI; the URL is
            // composed by the model from config('paperless.url'), so we
            // skip rows where the integration is disabled and the URL
            // would be null.
            'paperlessLinks' => $this->presentPaperlessLinks($item),
            // The Home Assistant entity this item is linked to (1:1), or null.
            // Written by the HA integration via the v1 API; shown read-only here
            // with an unlink control. Same "Connections" card as Paperless.
            'homeAssistantLink' => $this->presentHomeAssistantLink($item),
            // Active schedules + full history (completions and ad-hoc
            // repairs). Archived one-offs only surface through their entry.
            // The battery "Replace battery" forecast task is excluded — the
            // battery panel below owns that reminder.
            'maintenance' => [
                'tasks' => $item->maintenanceTasks()->active()
                    ->where('schedule_type', '!=', MaintenanceScheduleType::Forecast)
                    ->get()
                    ->map(fn (MaintenanceTask $task) => $this->maintenancePresenter->presentTask($task))
                    ->values(),
                'entries' => $item->maintenanceEntries()->with(['performer', 'task'])->get()
                    ->map(fn (MaintenanceEntry $entry) => $this->maintenancePresenter->presentEntry($entry))
                    ->values(),
            ],
            // Battery tracking: current level/type, depletion forecast, the
            // reminder, and the per-cycle reading series for the chart. The
            // panel renders only when the item has battery history.
            'battery' => [
                'summary' => $this->batteryPresenter->summary($item),
                'cycles' => $this->batteryPresenter->cycles($item),
            ],
            // Pending AI suggestions, shown beside the field each would change so
            // the photos that produced them are in view while deciding.
            'suggestions' => $item->proposals()
                ->pending()
                ->get()
                // The presenter needs the item for the stale check. We already
                // have it, so hand it over rather than let each proposal fetch
                // its own — which under Model::shouldBeStrict() is not an N+1
                // but a hard LazyLoadingViolationException.
                ->each(fn ($proposal) => $proposal->setRelation('item', $item))
                ->map($this->proposals->present(...))
                ->keyBy('field'),
            'activities' => $activities,
            // For the bulk-tag dialog launched from the Contents section's
            // Select mode. Sent unconditionally (tag count is small) so
            // toggling Select doesn't need an extra round-trip.
            'tags' => Tag::query()->orderBy('name')->get(['id', 'name', 'color']),
        ]);
    }

    /**
     * Search eligible destinations for moving an item. Fuzzy query via Scout,
     * excluding the item itself and its subtree (mirrors MoveItemRequest's cycle
     * guard). Defaults to rooms/containers; pass all=1 to search every item.
     * Loaded on demand so an item page doesn't have to ship every possible target.
     */
    public function moveTargets(Request $request, Item $item): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $includeItems = $request->boolean('all');
        $excluded = [$item->id, ...$item->descendantIds()];

        // No-query path: explicit Eloquent so we can alphabetic-order —
        // Scout's relevance ordering doesn't apply without a search term.
        if ($query === '') {
            $rows = Item::query()
                ->owned()
                ->when(! $includeItems, fn ($builder) => $builder->whereIn('type', [ItemType::Room->value, ItemType::Container->value]))
                ->whereNotIn('id', $excluded)
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'parent_id', 'name']);

            return response()->json(['targets' => $this->mapAsTargets($rows)]);
        }

        // Query path: push the filters down to Meili via Scout's
        // whereIn / whereNotIn — `id` and `type` are both filterable on
        // the items index (see config/scout.php). No more overfetch +
        // PHP reject.
        $rows = Item::search($query)
            ->whereNotIn('id', $excluded)
            ->when(! $includeItems, fn ($b) => $b->whereIn('type', [
                ItemType::Room->value,
                ItemType::Container->value,
            ]))
            ->take(25)
            ->get();

        return response()->json(['targets' => $this->mapAsTargets($rows)]);
    }

    /**
     * Candidate items to link as a related-item. Excludes self and any item
     * already linked to this one (re-linking is idempotent server-side, but
     * showing them in the picker would be confusing). Unlike moveTargets,
     * cycles aren't a concern, so descendants and ancestors are eligible.
     *
     * Search-only — the dialog never calls this without a query, so an
     * empty `q` returns an empty list rather than dumping every item.
     */
    public function relatedItemTargets(Request $request, Item $item): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            return response()->json(['targets' => []]);
        }

        $excluded = [$item->id, ...$item->relatedItems()->pluck('items.id')->all()];

        $rows = Item::search($query)
            ->whereNotIn('id', $excluded)
            ->take(25)
            ->get();

        return response()->json(['targets' => $this->mapAsTargets($rows)]);
    }

    /**
     * Shared serialisation for both target endpoints — picker dialogs only
     * need id, name and an ancestor path for the secondary line.
     *
     * @param  iterable<int, Item>  $items
     * @return array<int, array{id: int, name: string, path: string}>
     */
    private function mapAsTargets(iterable $items): array
    {
        $out = [];
        foreach ($items as $target) {
            $out[] = [
                'id' => $target->id,
                'name' => $target->name,
                // Ancestor path only ("Garage / Workbench"); empty for top-level.
                'path' => $target->locationPath(),
            ];
        }

        return $out;
    }

    public function edit(Item $item): Response
    {
        $item->load(['tags', 'images', 'customFieldValues.field', 'paperlessLinks', 'homeAssistantLink']);
        // The form asks what became of the contents when a container full of
        // things is marked sold, so it needs the count. Counted here rather
        // than left to the serialiser, which now reports only what the caller
        // actually asked the database for.
        $item->loadCount(['children' => fn ($query) => $query->owned()]);

        return Inertia::render('items/Edit', [
            'item' => ItemDetailResource::make($item)->resolve(),
            'tags' => Tag::query()->orderBy('name')->get(),
            'types' => $this->typeOptions(),
            'customFields' => $this->customFieldDefinitions(),
            'batteryTypes' => BatteryType::values(),
            // Tags the user may not remove from this item — the auto-managed
            // "Battery" tag while the item is battery-tracked. The picker
            // renders these as locked chips (the tag is re-added on the next
            // reading anyway).
            'lockedTagIds' => $this->lockedTagIds($item),
            // Paperless + Home Assistant links surface on Edit — that's where
            // the user can unlink. Show.vue lists the same links read-only.
            'paperlessLinks' => $this->presentPaperlessLinks($item),
            'homeAssistantLink' => $this->presentHomeAssistantLink($item),
        ]);
    }

    /**
     * Shape an item's PaperlessLink rows for the Inertia client. Rows whose
     * `paperlessUrl()` is null (the integration was disabled after the link
     * was created) are dropped so the front-end never gets a clickable chip
     * pointing at nowhere. Title/type are the cached snapshot columns —
     * null on rows the repair job hasn't touched yet, and the chips fall
     * back to the bare #id.
     *
     * @return Collection<int, array{document_id: int, url: string, title: ?string, type: ?string}>
     */
    private function presentPaperlessLinks(Item $item): Collection
    {
        return $item->paperlessLinks
            ->map(fn (PaperlessLink $link) => [
                'document_id' => $link->paperless_document_id,
                'url' => $link->paperlessUrl(),
                'title' => $link->document_title,
                'type' => $link->document_type,
            ])
            ->filter(fn (array $l) => $l['url'] !== null)
            ->values();
    }

    /**
     * The item's Home Assistant link as a flat array for the Show page, or
     * null when unlinked. `url` (the deep link to the HA device page) is
     * nullable — the UI falls back to the entity id when it's absent.
     *
     * @return array{entity_id: string|null, device_id: string|null, friendly_name: string|null, url: string|null}|null
     */
    private function presentHomeAssistantLink(Item $item): ?array
    {
        $link = $item->homeAssistantLink;

        if ($link === null) {
            return null;
        }

        return [
            'entity_id' => $link->ha_entity_id,
            'device_id' => $link->ha_device_id,
            'friendly_name' => $link->friendly_name,
            'url' => $link->url,
        ];
    }

    public function update(UpdateItemRequest $request, Item $item): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        $customFields = $data['custom_fields'] ?? [];
        $disposition = $data['contents_disposition'] ?? null;
        unset($data['tags'], $data['custom_fields'], $data['contents_disposition']);

        // Read before the write: afterwards the item is sold either way, and
        // there is no telling an edit to an old sale from the sale itself.
        $becomingSold = blank($item->sold_date) && filled($data['sold_date'] ?? null);

        $this->writer->update($item, $data, $tagIds);

        if ($becomingSold && $disposition !== null) {
            $affected = $this->writer->applySaleToContents($item, SaleDisposition::from($disposition));

            // One checkbox can retire a whole subtree, so say how much of one
            // it just moved rather than leaving the user to go and count.
            if ($affected > 0) {
                session()->flash('sale_contents', [
                    'disposition' => $disposition,
                    'count' => $affected,
                ]);
            }
        }
        $this->writer->syncCustomFields($item, $customFields);
        // Re-index now that custom fields are attached.
        $item->searchable();

        return to_route('items.show', $item);
    }

    public function destroy(Item $item): RedirectResponse
    {
        // Guard against deleting the room/container that household prefs
        // points at for Paperless intake — orphaning the preference would
        // silently drop future imports back to the top level. Admin has to
        // change the preference first; same shape as the box-tag guard in
        // TagController::destroy.
        if (Setting::int('paperless_parent_id') === $item->id) {
            throw ValidationException::withMessages([
                'item' => __('items.cannot_delete_paperless_parent'),
            ]);
        }

        $parentId = $item->parent_id;
        $this->writer->delete($item);

        return $parentId
            ? to_route('items.show', $parentId)
            : to_route('items.index');
    }

    public function move(MoveItemRequest $request, Item $item): RedirectResponse
    {
        $this->writer->move($item, $request->input('parent_id') !== null ? (int) $request->input('parent_id') : null);

        return back();
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string, details: bool}>
     */
    private function typeOptions(): array
    {
        return collect(ItemType::cases())
            ->map(fn (ItemType $type): array => $type->descriptor())
            ->values()
            ->all();
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
     * Tag ids the user may not detach from this item in the form — currently
     * just the auto-managed "Battery" tag while the item is battery-tracked.
     *
     * @return list<int>
     */
    private function lockedTagIds(Item $item): array
    {
        $batteryTagId = Setting::int('battery_tag_id');

        return $batteryTagId !== null && $item->batteryCycles()->exists()
            ? [$batteryTagId]
            : [];
    }
}
