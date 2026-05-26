<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Services\ActivityPresenter;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __construct(private readonly ActivityPresenter $presenter) {}

    public function __invoke(): Response
    {
        $byType = Item::query()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        // Estimated value of what's currently owned (sold items excluded).
        $value = (float) Item::query()->whereNull('sold_date')->sum('purchase_price');

        $recent = Item::query()
            ->with(['parent:id,name,type', 'primaryImage'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'parent_id', 'type', 'name', 'icon', 'created_at']);

        // Top 20 tags, most-used first — drives the clickable dashboard tag strip.
        $tags = Tag::query()
            ->withCount('items')
            ->orderByDesc('items_count')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'color']);

        // Top 20 rooms, fullest first — drives the clickable dashboard room strip.
        $rooms = Item::query()
            ->where('type', ItemType::Room)
            ->withCount('children')
            ->orderByDesc('children_count')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'icon'])
            ->map(fn (Item $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'icon' => $r->icon,
                'count' => $r->children_count,
            ]);

        $activity = Activity::query()
            ->with(['causer', 'subject'])
            ->latest()
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Activity $activity): array => $this->presenter->present($activity));

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => (int) $byType->sum(),
                'value' => $value,
                'rooms' => (int) ($byType[ItemType::Room->value] ?? 0),
                'containers' => (int) ($byType[ItemType::Container->value] ?? 0),
                'items' => (int) ($byType[ItemType::Item->value] ?? 0),
            ],
            'recent' => $recent->map(fn (Item $i): array => [
                'id' => $i->id,
                'name' => $i->name,
                'created_at_human' => $i->created_at?->diffForHumans(),
                'type' => [
                    'value' => $i->type->value,
                    'label' => $i->type->label(),
                    'icon' => $i->type->icon(),
                ],
                'thumb_url' => $i->primaryImage?->thumbUrl(),
                'icon' => $i->icon,
                'parent' => $i->parent ? [
                    'id' => $i->parent->id,
                    'name' => $i->parent->name,
                    'type' => [
                        'value' => $i->parent->type->value,
                        'label' => $i->parent->type->label(),
                        'icon' => $i->parent->type->icon(),
                    ],
                ] : null,
            ]),
            'tags' => $tags,
            'rooms' => $rooms,
            'activity' => $activity,
        ]);
    }
}
