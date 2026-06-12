<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        $tags = Tag::query()
            ->withCount('items')
            ->orderBy('name')
            ->get();

        return Inertia::render('tags/Index', [
            'tags' => $tags,
            // Auto-managed tags (box / Home Assistant / battery) can't be
            // deleted — the UI hides their delete control rather than letting
            // the user hit the validation error.
            'protectedTagIds' => array_values(array_filter([
                Setting::int('box_tag_id'),
                Setting::int('home_assistant_tag_id'),
                Setting::int('battery_tag_id'),
            ], fn (?int $id): bool => $id !== null)),
        ]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create($request->validated());

        return to_route('tags.index');
    }

    public function update(UpdateTagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return to_route('tags.index');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        // Guard against deleting the tag that the Box setting points at — it
        // would orphan the preference (sync([staleId]) would later throw a FK
        // violation on the next box-create). The admin has to change the
        // preference first; a one-trip-deletion is worth less than not having
        // to silently null user-configured settings.
        if (Setting::int('box_tag_id') === $tag->id) {
            throw ValidationException::withMessages([
                'tag' => __('tags.cannot_delete_box_tag'),
            ]);
        }

        // The auto-managed Home Assistant tag is protected once it's been
        // selected (recorded on the first device link), same as the Box tag.
        if (Setting::int('home_assistant_tag_id') === $tag->id) {
            throw ValidationException::withMessages([
                'tag' => __('tags.cannot_delete_home_assistant_tag'),
            ]);
        }

        // The auto-managed Battery tag is protected once it's been selected
        // (recorded the first time an item became battery-tracked).
        if (Setting::int('battery_tag_id') === $tag->id) {
            throw ValidationException::withMessages([
                'tag' => __('tags.cannot_delete_battery_tag'),
            ]);
        }

        $tag->delete();

        return to_route('tags.index');
    }
}
