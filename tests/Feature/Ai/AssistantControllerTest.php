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
