<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Services\ActivityPresenter;
use App\Services\InventoryStatistics;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ActivityPresenter $presenter,
        private readonly InventoryStatistics $stats,
    ) {}

    public function __invoke(): Response
    {
        // Counts/value/tag+room breakdowns are shared with the v1 API via
        // InventoryStatistics; the dashboard takes the top-20 strips.
        $byType = $this->stats->countsByType();
        $value = $this->stats->ownedValue();

        $recent = Item::query()
            ->with(['parent:id,name,type', 'primaryImage'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'parent_id', 'type', 'name', 'icon', 'created_at']);

        // Top 20 tags, most-used first — drives the clickable dashboard tag strip.
        $tags = $this->stats->tagsWithItemCounts(20);

        // Top 20 rooms, fullest first — drives the clickable dashboard room strip.
        $rooms = $this->stats->roomsWithChildCounts(20)
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
