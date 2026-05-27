<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\InventoryAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Contracts\Conversational;
use Tests\TestCase;

class AssistantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true, 'ai.conversations.generate_title' => false]);
    }

    public function test_guests_are_redirected(): void
    {
        $this->post('/assistant/messages', ['message' => 'hi'])->assertRedirect('/login');
    }

    public function test_it_is_gated_by_the_ai_flag(): void
    {
        config(['ai.enabled' => false]);
        InventoryAssistant::fake(['nope']);

        $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'hi'])
            ->assertStatus(503);
    }

    public function test_a_message_requires_content(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', [])
            ->assertStatus(422);
    }

    public function test_it_replies_and_persists_the_conversation(): void
    {
        InventoryAssistant::fake(['Found 2 drills.']);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/assistant/messages', ['message' => 'where are my drills?'])
            ->assertOk()
            ->assertJsonPath('reply', 'Found 2 drills.');

        $conversationId = $response->json('conversation_id');
        $this->assertNotNull($conversationId);

        $this->actingAs($user)
            ->getJson('/assistant/conversation')
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversationId)
            ->assertJsonFragment(['role' => 'user', 'content' => 'where are my drills?'])
            ->assertJsonFragment(['role' => 'assistant', 'content' => 'Found 2 drills.']);

        InventoryAssistant::assertPrompted(fn () => true);
    }

    public function test_it_replays_prior_messages_so_the_model_has_memory(): void
    {
        // The SDK only feeds stored history back to the model when the agent
        // implements Conversational (GeneratesText checks `instanceof Conversational`).
        // Without it, conversations persist but every turn starts blank.
        $this->assertInstanceOf(Conversational::class, new InventoryAssistant);

        InventoryAssistant::fake(['ok']);
        $user = User::factory()->create();

        $conversationId = $this->actingAs($user)
            ->postJson('/assistant/messages', ['message' => 'where is the drill?'])
            ->json('conversation_id');

        $history = (new InventoryAssistant)->forUser($user)->continue($conversationId, $user)->messages();

        $this->assertNotEmpty($history, 'Continuing a conversation must replay its stored messages.');
    }

    public function test_omitting_the_conversation_id_starts_a_fresh_thread(): void
    {
        // Backs the panel's "New chat" reset: dropping the conversation_id begins a
        // brand-new thread while the previous one stays saved (non-destructive).
        InventoryAssistant::fake(['one', 'two']);
        $user = User::factory()->create();

        $first = $this->actingAs($user)
            ->postJson('/assistant/messages', ['message' => 'first thread'])
            ->json('conversation_id');

        $second = $this->actingAs($user)
            ->postJson('/assistant/messages', ['message' => 'fresh start'])
            ->json('conversation_id');

        $this->assertNotSame($first, $second);
        $this->assertSame(2, $user->conversations()->count());
    }

    public function test_a_thread_idle_past_the_reset_window_is_not_rehydrated(): void
    {
        config(['ai.chat_reset_after_hours' => 3]);
        InventoryAssistant::fake(['hello']);
        $user = User::factory()->create();

        $conversationId = $this->actingAs($user)
            ->postJson('/assistant/messages', ['message' => 'an old question'])
            ->json('conversation_id');

        // Age the thread past the 3-hour window.
        $user->conversations()->whereKey($conversationId)->update(['updated_at' => now()->subHours(4)]);

        $this->actingAs($user)
            ->getJson('/assistant/conversation')
            ->assertOk()
            ->assertJsonPath('conversation_id', null)
            ->assertJsonPath('messages', []);
    }

    public function test_the_reset_window_is_configurable(): void
    {
        // 0 disables the timeout, so even a very old thread is resumed.
        config(['ai.chat_reset_after_hours' => 0]);
        InventoryAssistant::fake(['hello']);
        $user = User::factory()->create();

        $conversationId = $this->actingAs($user)
            ->postJson('/assistant/messages', ['message' => 'an old question'])
            ->json('conversation_id');

        $user->conversations()->whereKey($conversationId)->update(['updated_at' => now()->subDays(30)]);

        $this->actingAs($user)
            ->getJson('/assistant/conversation')
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversationId)
            ->assertJsonFragment(['role' => 'user', 'content' => 'an old question']);
    }

    public function test_a_user_cannot_continue_another_users_conversation(): void
    {
        InventoryAssistant::fake(['ok', 'ok']);

        $alice = User::factory()->create();
        $aliceConversation = $this->actingAs($alice)
            ->postJson('/assistant/messages', ['message' => 'secret'])
            ->json('conversation_id');

        $bob = User::factory()->create();
        $bobConversation = $this->actingAs($bob)
            ->postJson('/assistant/messages', ['message' => 'hello', 'conversation_id' => $aliceConversation])
            ->json('conversation_id');

        $this->assertNotSame($aliceConversation, $bobConversation);
    }
}
