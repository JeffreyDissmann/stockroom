<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
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
        $tag->delete();

        return to_route('tags.index');
    }
}
