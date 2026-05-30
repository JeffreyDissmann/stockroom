<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Http\Requests\Household\UpdatePreferencesRequest;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-household preferences page — admin-editable settings stored in the
 * `settings` key/value table (not env-driven config like CURRENCY). v1 holds
 * just the box tag pointer; future settings can land here without new routes.
 */
class PreferencesController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('household/Preferences', [
            'preferences' => [
                'box_tag_id' => Setting::get('box_tag_id'),
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
        ]);
    }

    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        // Validated `box_tag_id` is either a valid tag id or null (admin opted
        // out of auto-tagging). Cast preserves type — Setting::get returns null
        // when the setting is missing, so we don't want a stored 0.
        $tagId = $request->input('box_tag_id');
        Setting::set('box_tag_id', $tagId === null ? null : (int) $tagId);

        return back();
    }
}
