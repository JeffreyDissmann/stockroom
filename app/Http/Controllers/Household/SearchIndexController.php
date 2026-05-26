<?php

declare(strict_types=1);

namespace App\Http\Controllers\Household;

use App\Http\Controllers\Controller;
use App\Jobs\RebuildSearchIndexJob;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class SearchIndexController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('household/SearchIndex', [
            'status' => Cache::get(RebuildSearchIndexJob::STATUS_KEY),
            'total' => Item::count(),
            'semantic' => filled(config('scout.meilisearch.hybrid.embedder')),
        ]);
    }

    public function rebuild(): RedirectResponse
    {
        Cache::put(
            RebuildSearchIndexJob::STATUS_KEY,
            ['state' => 'running', 'done' => 0, 'total' => Item::count()],
            now()->addHour(),
        );

        RebuildSearchIndexJob::dispatch();

        return back();
    }
}
