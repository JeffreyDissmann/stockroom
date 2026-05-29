<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_redirects_respect_x_forwarded_proto_from_reverse_proxy(): void
    {
        // Stockroom is deployed behind a reverse proxy that terminates TLS;
        // without trustProxies the request looks HTTP to Laravel, so route()
        // generates http:// URLs and the browser blocks cross-scheme XHRs.
        // Pin this down: an HTTP request with X-Forwarded-Proto: https must
        // produce https:// absolute URLs (here via url()->current()).
        $response = $this->withServerVariables([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'stockroom.example.com',
        ])->get('/login');

        $response->assertOk();
        $this->assertTrue(request()->isSecure(), 'request()->isSecure() must be true behind a TLS-terminating proxy.');
        $this->assertStringStartsWith('https://', url()->current());
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
