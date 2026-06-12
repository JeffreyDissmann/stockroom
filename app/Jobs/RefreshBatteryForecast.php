<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Item;
use App\Services\Battery\BatteryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Recompute an item's battery depletion forecast off the request thread.
 *
 * Recording a level is a cheap insert, but the regression that follows it
 * (a least-squares fit pooled across recent cycles) shouldn't make Home
 * Assistant wait — readings arrive frequently. So BatteryService records the
 * sample synchronously and dispatches this; the worker refreshes the "Replace
 * battery" reminder and caches the projection snapshot on the open cycle.
 *
 * Tries(1) — the source readings are already on disk, so a transient worker
 * hiccup needs no retry; the next reading dispatches another refresh anyway.
 */
#[Tries(1)]
class RefreshBatteryForecast implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $itemId) {}

    public function handle(BatteryService $battery): void
    {
        $item = Item::query()->find($this->itemId);

        if ($item !== null) {
            $battery->refreshForecast($item);
        }
    }
}
