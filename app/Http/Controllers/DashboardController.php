<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\ItemProposal;
use App\Models\MaintenanceTask;
use App\Services\ActivityPresenter;
use App\Services\InventoryStatistics;
use App\Services\Maintenance\MaintenancePresenter;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ActivityPresenter $presenter,
        private readonly InventoryStatistics $stats,
        private readonly MaintenancePresenter $maintenancePresenter,
    ) {}

    public function __invoke(): Response
    {
        // Counts/value/tag+room breakdowns are shared with the v1 API via
        // InventoryStatistics; the dashboard takes the top-20 strips.
        $byType = $this->stats->countsByType();
        $value = $this->stats->ownedValue();

        // Full rows rather than a column list: ItemResource reads sold_date and
        // description, and under Model::shouldBeStrict() a column left out of
        // the select is a hard error rather than a null. Six rows, so the
        // narrower select bought nothing.
        $recent = Item::query()
            ->owned()
            ->with(['parent', 'primaryImage'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

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

        // Maintenance needing attention — the shared pipeline the digest
        // uses too. The card shows the five most urgent; the count tells
        // the user whether the list was truncated.
        $attention = MaintenanceTask::needingAttention();

        // Two different states, both worth knowing about from here: suggestions
        // waiting on a decision, and photos Stockroom has not looked at yet.
        // Only the first has rows to show — the second is a nudge to press the
        // button on the queue page.
        $suggested = Item::query()
            ->whereHas('proposals', fn ($query) => $query->pending())
            ->withCount(['proposals as pending_count' => fn ($query) => $query->pending()])
            ->orderByDesc('pending_count')
            ->limit(5)
            ->get(['id', 'name']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => (int) $byType->sum(),
                'value' => $value,
                'rooms' => (int) ($byType[ItemType::Room->value] ?? 0),
                'containers' => (int) ($byType[ItemType::Container->value] ?? 0),
                'items' => (int) ($byType[ItemType::Item->value] ?? 0),
            ],
            'recent' => $recent->map(fn (Item $item): array => [
                ...ItemResource::make($item)->resolve(),
                'created_at_human' => $item->created_at?->diffForHumans(),
            ]),
            'tags' => $tags,
            'rooms' => $rooms,
            'activity' => $activity,
            'proposals' => [
                'pending' => ItemProposal::pending()->count(),
                'unreviewed' => config('ai.enabled') ? Item::awaitingPhotoReview()->count() : 0,
                'items' => $suggested->map(fn (Item $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'count' => (int) $item->pending_count,
                ]),
            ],
            'maintenance' => [
                'count' => $attention->count(),
                'tasks' => $attention->take(5)->values()->map(fn (MaintenanceTask $task): array => [
                    ...$this->maintenancePresenter->presentTask($task),
                    'item' => [
                        'id' => $task->item->id,
                        'name' => $task->item->name,
                    ],
                ]),
            ],
        ]);
    }
}
