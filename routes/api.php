<?php

declare(strict_types=1);

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
    });
