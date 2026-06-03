<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HomeAssistantLinkController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StatisticsController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Token-authenticated REST API consumed by the Home Assistant integration
| (see docs/api.md). Stateless Sanctum personal access tokens only — these
| routes are in the `api` middleware group, so there is no session or CSRF;
| auth is the `Authorization: Bearer <token>` header. Tokens carry `read`
| and/or `write` abilities; write routes additionally require `write`.
|
*/

Route::middleware(['auth:sanctum', 'throttle:api'])
    ->prefix('v1')
    ->as('api.v1.')
    ->group(function () {
        // Token introspection — lets the HA config flow validate a pasted
        // token and show "connected as".
        Route::get('user', UserController::class)->name('user');

        // Read endpoints (a `read` token suffices).
        Route::get('statistics', StatisticsController::class)->name('statistics');
        Route::get('items', [ItemController::class, 'index'])->name('items.index');
        Route::get('items/{item}', [ItemController::class, 'show'])->name('items.show');
        Route::get('rooms', RoomController::class)->name('rooms.index');
        Route::get('tags', TagController::class)->name('tags.index');
        Route::get('search', SearchController::class)->name('search');

        // Write endpoints — require a token with the `write` ability.
        Route::middleware('abilities:write')->group(function () {
            Route::post('items', [ItemController::class, 'store'])->name('items.store');
            Route::patch('items/{item}', [ItemController::class, 'update'])->name('items.update');

            Route::put('items/{item}/home-assistant-link', [HomeAssistantLinkController::class, 'update'])
                ->name('items.home-assistant-link.update');
            Route::delete('items/{item}/home-assistant-link', [HomeAssistantLinkController::class, 'destroy'])
                ->name('items.home-assistant-link.destroy');
        });
    });
