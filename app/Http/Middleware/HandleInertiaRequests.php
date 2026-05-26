<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Brave\BraveImageSearchClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
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
        ]);
    }
}
