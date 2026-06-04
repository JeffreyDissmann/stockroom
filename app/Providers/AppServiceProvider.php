<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AssistantContext;
use App\Models\User;
use App\Services\Paperless\PaperlessClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageManager::class, fn () => new ImageManager(new Driver));

        // One per request so the assistant controller and its write tools share
        // the active conversation id (used to attach a pending uploaded image).
        $this->app->scoped(AssistantContext::class);

        // PaperlessClient takes scalar credentials in its constructor, so the
        // container can't auto-resolve it. Binding via fromConfig() lets the
        // intake job ask for it by type-hint; null binding (when the
        // integration is disabled) surfaces as a clear container error in
        // dev, which is exactly what we want — disabled integrations should
        // never reach a job that depends on the client.
        $this->app->bind(PaperlessClient::class, function () {
            $client = PaperlessClient::fromConfig();

            if ($client === null) {
                throw new \RuntimeException('Paperless integration is not configured.');
            }

            return $client;
        });
    }

    public function boot(): void
    {
        Model::shouldBeStrict();

        // Household management (members, custom fields, tags, backup/import/reindex)
        // is reserved for admins; everyone else manages inventory items only.
        Gate::define('admin', fn (User $user): bool => $user->is_admin);

        // The v1 API (consumed by the Home Assistant integration) is rate
        // limited per token holder, falling back to the client IP for the few
        // unauthenticated edges. Generous enough for a polling integration.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
