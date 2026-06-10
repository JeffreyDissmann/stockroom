<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreBatteryChangeRequest;
use App\Http\Requests\Api\V1\StoreBatteryReadingRequest;
use App\Http\Resources\Api\V1\BatteryResource;
use App\Models\Item;
use App\Services\Battery\BatteryService;
use Illuminate\Http\JsonResponse;

/**
 * Battery tracking over the API, for Home Assistant: push level readings,
 * record explicit swaps, and read the current state + depletion forecast.
 * The battery type itself is a plain item field (see ItemController::update).
 */
class BatteryController extends Controller
{
    public function __construct(private readonly BatteryService $battery) {}

    /**
     * Current level, type, the live depletion projection and the reminder.
     */
    public function show(Item $item): BatteryResource
    {
        return new BatteryResource($item);
    }

    /**
     * Record a level sample (the main HA path) and return the updated state.
     */
    public function storeReading(StoreBatteryReadingRequest $request, Item $item): JsonResponse
    {
        $this->battery->recordReading(
            $item,
            (int) $request->validated('percent'),
            $request->validated('recorded_at'),
        );

        return (new BatteryResource($item))->response()->setStatusCode(201);
    }

    /**
     * Record an explicit battery swap and return the updated state.
     */
    public function storeChange(StoreBatteryChangeRequest $request, Item $item): JsonResponse
    {
        $this->battery->changeBattery(
            $item,
            $request->validated('changed_at'),
            $request->validated('notes'),
            $request->user()?->id,
        );

        return (new BatteryResource($item))->response()->setStatusCode(201);
    }
}
