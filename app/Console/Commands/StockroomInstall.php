<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * First-boot bootstrap for self-hosted Docker deployments.
 *
 * Reads STOCKROOM_ADMIN_EMAIL + STOCKROOM_ADMIN_PASSWORD and creates the
 * initial admin user — but only when no users exist yet. Subsequent runs
 * are a no-op so the entrypoint can call us unconditionally on every boot.
 *
 * Designed to fail soft: missing env vars or invalid input log a warning and
 * exit successfully, because crashing the entrypoint would lock the operator
 * out of the running container. The fallback for them is then to register
 * via the normal invite flow once they've fixed the env.
 */
class StockroomInstall extends Command
{
    protected $signature = 'stockroom:install
        {--email= : Admin email (overrides STOCKROOM_ADMIN_EMAIL)}
        {--password= : Admin password (overrides STOCKROOM_ADMIN_PASSWORD)}
        {--name= : Admin display name (defaults to "Admin")}
        {--force : Create the admin even if other users already exist}';

    protected $description = 'Bootstrap the first admin user on a fresh self-hosted installation';

    public function handle(): int
    {
        if (User::query()->exists() && ! $this->option('force')) {
            $this->info('stockroom:install — users already exist, nothing to do.');

            return self::SUCCESS;
        }

        $email = $this->option('email') ?: env('STOCKROOM_ADMIN_EMAIL');
        $password = $this->option('password') ?: env('STOCKROOM_ADMIN_PASSWORD');
        $name = $this->option('name') ?: (env('STOCKROOM_ADMIN_NAME') ?: 'Admin');

        if (! $email || ! $password) {
            $this->warn('stockroom:install — STOCKROOM_ADMIN_EMAIL and STOCKROOM_ADMIN_PASSWORD must both be set; skipping.');

            return self::SUCCESS;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                // Match the registration validator so an admin seeded here
                // could pass the same rules if they were registering normally.
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->warn("stockroom:install — {$message}");
            }

            return self::SUCCESS;
        }

        // email_verified_at isn't fillable (matches the registration flow that
        // uses forceFill for the same field), so do it in two steps.
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info("stockroom:install — created admin {$user->email} (id {$user->id}).");
        $this->warn('Clear STOCKROOM_ADMIN_PASSWORD from your environment now; it has no further effect.');

        return self::SUCCESS;
    }
}
