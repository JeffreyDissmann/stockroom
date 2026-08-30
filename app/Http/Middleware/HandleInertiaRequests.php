<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Brave\BraveImageSearchClient;
use App\Support\AppVersion;
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
        'activity', 'tags', 'settings', 'household', 'members', 'login', 'enums', 'assistant', 'auth_context', 'auth_form', 'maintenance', 'proposals',
    ];

    /**
     * Session keys forwarded to the client as the `flash` prop.
     *
     * Inertia does not share session flash automatically, so a key a
     * controller flashes but that is missing here silently never reaches the
     * page. That is exactly how the bulk-move Undo toast became dead code:
     * BulkController flashed `bulk_result` from four places and the watcher
     * in BulkActionBar.vue never once fired. FlashContractTest asserts this
     * list stays in step with what the controllers actually flash.
     *
     * `status` is deliberately absent — the auth controllers pass it as an
     * explicit page prop (Breeze convention) rather than through here.
     *
     * @var list<string>
     */
    private const FLASH_KEYS = [
        // Item/tag/image counts written by a backup import.
        'backup',
        // The source item's name, for the one-shot banner on the new box's
        // Show page.
        'box_created_for',
        // Action, count and the previous parent map that powers the 6s Undo
        // toast after a bulk move.
        'bulk_result',
        // 'sent' | 'failed' — feedback after emailing an invite.
        'invitation_mail',
        // How many Paperless documents the relink-all run covered.
        'paperless_relink_count',
        // How many items went with a sold container, or moved up a level when
        // its contents were kept.
        'sale_contents',
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
        return array_merge(parent::share($request), [
            'name' => config('app.name'),
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
            'flash' => $this->flash($request),
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
     * One-shot session values for the current request, keyed by FLASH_KEYS.
     * Absent keys come through as null so the client can watch a stable shape.
     *
     * @return array<string, mixed>
     */
    private function flash(Request $request): array
    {
        $session = $request->session();

        return collect(self::FLASH_KEYS)
            ->mapWithKeys(fn (string $key): array => [$key => $session->get($key)])
            ->all();
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
