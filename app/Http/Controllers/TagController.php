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
        if (Setting::get('box_tag_id') === $tag->id) {
            throw ValidationException::withMessages([
                'tag' => __('tags.cannot_delete_box_tag'),
            ]);
        }

        $tag->delete();

        return to_route('tags.index');
    }
}
