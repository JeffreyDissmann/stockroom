<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_responses_carry_rate_limit_headers(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['read']);

        // The `api` limiter (120/min) is applied via throttle:api.
        $this->getJson('/api/v1/statistics')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '120');
    }

    public function test_exceeding_the_limit_returns_429(): void
    {
        // Tighten the named limiter for this test so we don't have to send 120
        // requests. The array cache persists across requests within the test,
        // so the counter carries over.
        RateLimiter::for('api', fn () => Limit::perMinute(1));
        Sanctum::actingAs(User::factory()->create(), ['read']);

        $this->getJson('/api/v1/statistics')->assertOk();
        $this->getJson('/api/v1/statistics')->assertStatus(429);
    }
}
