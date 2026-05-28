<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StockroomInstallTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_first_admin_from_options(): void
    {
        $this->artisan('stockroom:install', [
            '--email' => 'admin@example.test',
            '--password' => 'secret-password',
            '--name' => 'Operator',
        ])
            ->expectsOutputToContain('created admin admin@example.test')
            ->assertExitCode(0);

        $user = User::firstWhere('email', 'admin@example.test');
        $this->assertNotNull($user);
        $this->assertSame('Operator', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_it_is_a_no_op_when_users_already_exist(): void
    {
        User::factory()->create();

        $this->artisan('stockroom:install', [
            '--email' => 'second@example.test',
            '--password' => 'secret-password',
        ])
            ->expectsOutputToContain('users already exist')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('users', ['email' => 'second@example.test']);
    }

    public function test_force_seeds_even_when_users_exist(): void
    {
        User::factory()->create();

        $this->artisan('stockroom:install', [
            '--email' => 'second@example.test',
            '--password' => 'secret-password',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'second@example.test', 'is_admin' => true]);
    }

    public function test_it_skips_softly_when_env_vars_are_missing(): void
    {
        // Neither options nor env vars are provided. We expect a warning and a
        // clean exit so the container entrypoint stays usable.
        $this->artisan('stockroom:install')
            ->expectsOutputToContain('must both be set')
            ->assertExitCode(0);

        $this->assertSame(0, User::query()->count());
    }

    public function test_it_skips_softly_on_invalid_input(): void
    {
        $this->artisan('stockroom:install', [
            '--email' => 'not-an-email',
            '--password' => 'short',
        ])->assertExitCode(0);

        $this->assertSame(0, User::query()->count());
    }
}
