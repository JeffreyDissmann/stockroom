<?php

declare(strict_types=1);

namespace App\Providers;

use App\Ai\AssistantContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
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
    }

    public function boot(): void
    {
        Model::shouldBeStrict();

        // Household management (members, custom fields, tags, backup/import/reindex)
        // is reserved for admins; everyone else manages inventory items only.
        Gate::define('admin', fn (User $user): bool => $user->is_admin);
    }
}
