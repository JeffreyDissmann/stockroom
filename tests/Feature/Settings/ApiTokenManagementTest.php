<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_authentication(): void
    {
        $this->get('/settings/api-tokens')->assertRedirect('/login');
    }

    public function test_index_lists_the_users_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('Home Assistant', ['read', 'write']);

        $this->actingAs($user)
            ->get('/settings/api-tokens')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/ApiTokens')
                ->has('tokens', 1)
                ->where('tokens.0.name', 'Home Assistant')
                ->where('tokens.0.abilities', ['read', 'write'])
                ->where('plainTextToken', null));
    }

    public function test_store_creates_a_token_and_flashes_the_plaintext_once(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', [
                'name' => 'Home Assistant',
                'abilities' => ['read', 'write'],
            ]);

        $response->assertRedirect('/settings/api-tokens')
            ->assertSessionHas('plainTextToken');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Home Assistant',
        ]);

        $token = $user->tokens()->sole();
        $this->assertSame(['read', 'write'], $token->abilities);

        // The displayed token has the Sanctum "{id}|" prefix stripped...
        $plainText = session('plainTextToken');
        $this->assertStringNotContainsString('|', $plainText);

        // ...and still authenticates against the API (Sanctum resolves the
        // bare secret), proving the stripped token is fully usable.
        $this->getJson('/api/v1/user', ['Authorization' => "Bearer {$plainText}"])
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_store_requires_a_name_and_at_least_one_ability(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', ['abilities' => []])
            ->assertSessionHasErrors(['name', 'abilities']);
    }

    public function test_store_rejects_unknown_abilities(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->post('/settings/api-tokens', ['name' => 'X', 'abilities' => ['delete']])
            ->assertSessionHasErrors('abilities.0');
    }

    public function test_destroy_revokes_a_token(): void
    {
        $user = User::factory()->create();
        $tokenId = $user->createToken('Home Assistant', ['read'])->accessToken->id;

        $this->actingAs($user)
            ->from('/settings/api-tokens')
            ->delete("/settings/api-tokens/{$tokenId}")
            ->assertRedirect('/settings/api-tokens');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $tokenId = $other->createToken('Theirs', ['read'])->accessToken->id;

        $this->actingAs($owner)
            ->delete("/settings/api-tokens/{$tokenId}")
            ->assertRedirect();

        // Scoped to the actor's own tokens, so the other user's survives.
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
    }
}
