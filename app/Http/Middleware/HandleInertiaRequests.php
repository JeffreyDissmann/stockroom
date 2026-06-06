<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Brave\BraveImageSearchClient;
use App\Support\AppVersion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Translation groups (lang/<locale>/<group>.php) exposed to the frontend.
     * Framework files (validation, auth, passwords) are intentionally excluded.
     *
     * @var list<string>
     */
    /**
     * Note: `auth` is intentionally not here. Laravel ships its own
     * `auth.php` ("These credentials do not match…") and shipping that
     * to the JS layer would let untranslated framework strings leak
     * onto the auth pages. The login-page context copy lives in its
     * own `auth_context` group instead.
     */
    private const TRANSLATION_GROUPS = [
        'common', 'nav', 'dashboard', 'items', 'search',
        'activity', 'tags', 'settings', 'household', 'members', 'login', 'enums', 'assistant', 'auth_context', 'maintenance',
    ];

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'currency' => [
                'code' => config('stockroom.currency.code'),
                'locale' => config('stockroom.currency.locale'),
            ],
            'features' => [
                'imageSearch' => BraveImageSearchClient::isConfigured(),
                'ai' => (bool) config('ai.enabled'),
                // Paperless is enabled only when BOTH URL and token are set —
                // either alone is a half-configured install that would 401 or
                // hit a connection error. Server-side routes mirror this gate
                // via EnsurePaperlessEnabled (404).
                'paperless' => filled(config('paperless.url')) && filled(config('paperless.token')),
            ],
            'flash' => [
                'backup' => $request->session()->get('backup'),
                // Surfaced as a one-shot banner on the new box's Show page —
                // the value is the source item's name (or null if not
                // arriving from a fresh box creation).
                'box_created_for' => $request->session()->get('box_created_for'),
            ],
            'locale' => app()->getLocale(),
            'translations' => $this->translations(),
            // Build info for the login-page context panel + future "about"
            // surfaces. Tag and sha can independently be null on dev or in
            // freshly-cloned trees without git metadata; the frontend hides
            // the chip rather than rendering "unknown".
            'version' => AppVersion::current(),
        ]);
    }

    /**
     * Flattened dot-key map of the active locale's UI strings, with English as
     * the base so any untranslated key falls back to its English value.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        $locale = app()->getLocale();
        $fallback = config('app.fallback_locale');

        $messages = [];

        foreach (self::TRANSLATION_GROUPS as $group) {
            $base = Lang::get($group, [], $fallback);
            $active = $locale === $fallback ? $base : Lang::get($group, [], $locale);

            if (! is_array($base)) {
                continue;
            }

            $merged = is_array($active) ? array_replace_recursive($base, $active) : $base;

            $messages += Arr::dot($merged, "{$group}.");
        }

        return $messages;
    }
}
