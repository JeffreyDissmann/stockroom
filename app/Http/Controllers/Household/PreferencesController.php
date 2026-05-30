<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsurePaperlessEnabled;
use App\Http\Requests\Household\UpdatePreferencesRequest;
use App\Jobs\RelinkAllPaperlessDocumentsJob;
use App\Models\Item;
use App\Models\PaperlessLink;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
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
            // Live status for the "Re-link all documents" job. Polled by
            // the page while state==='running'; null when no run has
            // happened (or its TTL expired).
            'relinkStatus' => Cache::get(RelinkAllPaperlessDocumentsJob::STATUS_KEY),
        ]);
    }

    /**
     * Operator repair (#7): dispatches a background job that walks every
     * distinct Paperless doc id we have local items linked to and re-applies
     * the Stockroom annotation (linked tag + `Stockroom URL` custom field).
     * Idempotent — safe to re-run.
     *
     * Queued rather than inline because a household with hundreds of linked
     * docs and a slow Paperless instance could blow past PHP's request
     * timeout. Returns immediately with a flash count so the admin sees
     * "started for N documents".
     */
    #[Middleware(EnsurePaperlessEnabled::class)]
    public function relinkAllPaperless(): RedirectResponse
    {
        $count = PaperlessLink::query()
            ->distinct()
            ->count('paperless_document_id');

        if ($count > 0) {
            // Seed the status as 'running' so the UI shows the bar
            // immediately on redirect — without it, the user sees "no
            // status" until the worker actually picks up the job.
            Cache::put(RelinkAllPaperlessDocumentsJob::STATUS_KEY, [
                'state' => 'running',
                'done' => 0,
                'failed' => 0,
                'total' => $count,
            ], now()->addHour());

            RelinkAllPaperlessDocumentsJob::dispatch();
        }

        return back()->with('paperless_relink_count', $count);
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
