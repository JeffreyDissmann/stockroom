<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $byType = Item::query()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $recent = Item::query()
            ->with('parent:id,name,type')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get(['id', 'parent_id', 'type', 'name', 'created_at']);

        $rooms = Item::query()
            ->where('type', ItemType::Room)
            ->withCount('children')
            ->orderBy('name')
            ->get(['id', 'type', 'name'])
            ->map(fn (Item $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'children_count' => $r->children_count,
                'type' => [
                    'value' => $r->type->value,
                    'label' => $r->type->label(),
                    'icon' => $r->type->icon(),
                ],
            ]);

        $tags = Tag::query()
            ->withCount('items')
            ->orderByDesc('items_count')
            ->limit(6)
            ->get(['id', 'name', 'slug', 'color']);

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => (int) $byType->sum(),
                'rooms' => (int) ($byType[ItemType::Room->value] ?? 0),
                'containers' => (int) ($byType[ItemType::Container->value] ?? 0),
                'items' => (int) ($byType[ItemType::Item->value] ?? 0),
            ],
            'recent' => $recent->map(fn (Item $i) => [
                'id' => $i->id,
                'name' => $i->name,
                'created_at_human' => $i->created_at?->diffForHumans(),
                'type' => [
                    'value' => $i->type->value,
                    'label' => $i->type->label(),
                    'icon' => $i->type->icon(),
                ],
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
            'rooms' => $rooms,
            'tags' => $tags,
        ]);
    }
}
