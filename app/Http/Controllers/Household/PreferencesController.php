<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Household\UpdatePreferencesRequest;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-household preferences page — admin-editable settings stored in the
 * `settings` key/value table (not env-driven config like CURRENCY).
 */
class PreferencesController extends Controller
{
    public function edit(): Response
    {
        $parentId = Setting::int('paperless_parent_id');
        $selectedParent = $parentId === null
            ? null
            : Item::query()
                ->whereKey($parentId)
                ->whereIn('type', [ItemType::Room->value, ItemType::Container->value])
                ->first(['id', 'name', 'type']);

        return Inertia::render('household/Preferences', [
            'preferences' => [
                'box_tag_id' => Setting::get('box_tag_id'),
                'paperless_parent_id' => $parentId,
            ],
            'tags' => Tag::query()
                ->orderBy('name')
                ->get(['id', 'name', 'color'])
                ->map(fn (Tag $tag): array => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])
                ->values(),
            // Just the currently-selected parent (if any) so the picker can
            // hydrate its label without an extra round-trip. Candidates come
            // from `paperlessParentTargets` on demand — see that action.
            'selectedParent' => $selectedParent === null
                ? null
                : [
                    'id' => $selectedParent->id,
                    'name' => $selectedParent->name,
                    'type' => $selectedParent->type->value,
                ],
        ]);
    }

    /**
     * Search candidates for the Paperless intake parent (rooms + containers
     * only). Mirrors items.move-targets: blank query returns the top-N
     * alphabetical, otherwise Scout-search by name. Caps at 25 — the picker
     * is a "find a known place", not a browse-everything UI.
     */
    public function paperlessParentTargets(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if ($query === '') {
            $rows = Item::query()
                ->whereIn('type', [ItemType::Room->value, ItemType::Container->value])
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'name', 'type']);
        } else {
            $rows = Item::search($query)
                ->whereIn('type', [ItemType::Room->value, ItemType::Container->value])
                ->take(25)
                ->get();
        }

        return response()->json([
            'targets' => $rows->map(fn (Item $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'type' => $item->type->value,
            ])->values(),
        ]);
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        // Validated values are either a valid id or null (admin opted out).
        // Cast preserves type — Setting::get returns null when the setting is
        // missing, so we don't want a stored 0.
        $tagId = $request->input('box_tag_id');
        Setting::set('box_tag_id', $tagId === null ? null : (int) $tagId);

        $parentId = $request->input('paperless_parent_id');
        Setting::set('paperless_parent_id', $parentId === null ? null : (int) $parentId);

        return back();
    }
}
