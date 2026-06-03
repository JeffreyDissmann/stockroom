<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiTokenRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access token management for the v1 API (consumed by the Home
 * Assistant integration). Tokens are issued with `read` and/or `write`
 * abilities; the plaintext is shown exactly once, right after creation.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/ApiTokens', [
            'tokens' => $request->user()->tokens()
                ->latest()
                ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'last_used_at' => $token->last_used_at?->diffForHumans(),
                    'created_at' => $token->created_at?->toDayDateTimeString(),
                ]),
            // Flashed by store() immediately after creation, then gone — this
            // is the only time the plaintext token is ever exposed.
            'plainTextToken' => $request->session()->get('plainTextToken'),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $token = $request->user()->createToken($validated['name'], $validated['abilities']);

        // Sanctum issues "{id}|{secret}"; the leading id is only a lookup hint —
        // findToken() resolves the bare secret via the uniquely-indexed token
        // column. Strip it so the user copies a clean token.
        $plainTextToken = Str::after($token->plainTextToken, '|');

        return to_route('api-tokens.index')->with('plainTextToken', $plainTextToken);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $request->user()->tokens()->whereKey($token)->delete();

        return back();
    }
}
