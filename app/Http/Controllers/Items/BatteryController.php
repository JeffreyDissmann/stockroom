<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Battery\ChangeBatteryRequest;
use App\Models\Item;
use App\Services\Battery\BatteryService;
use Illuminate\Http\RedirectResponse;

/**
 * The item page's battery actions. Level readings come from Home Assistant
 * over the API; the web UI only needs the manual "Change battery" button for
 * when you swap a battery before HA reports the fresh level.
 */
class BatteryController extends Controller
{
    public function __construct(private readonly BatteryService $battery) {}

    /**
     * Record a manual battery swap: closes the current cycle, opens a fresh
     * one and completes the "Replace battery" reminder.
     */
    public function change(ChangeBatteryRequest $request, Item $item): RedirectResponse
    {
        $this->battery->changeBattery(
            $item,
            $request->validated('changed_at'),
            $request->validated('notes'),
            $request->user()->id,
        );

        return back();
    }
}
