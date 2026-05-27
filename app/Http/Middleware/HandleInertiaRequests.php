<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Brave\BraveImageSearchClient;
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
    private const TRANSLATION_GROUPS = [
        'common', 'nav', 'dashboard', 'items', 'search',
        'activity', 'tags', 'settings', 'household', 'members', 'login', 'enums',
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
            ...parent::share($request),
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
            ],
            'flash' => [
                'backup' => $request->session()->get('backup'),
            ],
            'locale' => app()->getLocale(),
            'translations' => $this->translations(),
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
