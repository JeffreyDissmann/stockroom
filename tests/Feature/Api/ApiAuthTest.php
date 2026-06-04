<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized();
    }

    public function test_acting_as_token_user_returns_account(): void
    {
        $user = User::factory()->create(['name' => 'Jeff', 'email' => 'jeff@example.test']);

        Sanctum::actingAs($user, ['read']);

        $this->getJson('/api/v1/user')
            ->assertOk()
            ->assertExactJson(['id' => $user->id, 'name' => 'Jeff', 'email' => 'jeff@example.test']);
    }

    public function test_raw_bearer_token_authenticates(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('home-assistant', ['read'])->plainTextToken;

        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_invalid_bearer_token_is_rejected(): void
    {
        $this->getJson('/api/v1/user', ['Authorization' => 'Bearer not-a-real-token'])
            ->assertUnauthorized();
    }
}
