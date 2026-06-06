<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-user notification preferences. Currently just the maintenance
 * digest opt-in; future channels (Telegram, Home Assistant push) get
 * their settings here too.
 */
class NotificationController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('settings/Notifications');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'maintenance_digest_opt_in' => ['required', 'boolean'],
        ]);

        $request->user()->update($validated);

        return back();
    }
}
