<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\InventoryAssistant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Tests\TestCase;

class ForgetOldConversationsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insert a conversation (and one message) whose updated_at is the given moment.
     */
    private function seedConversation(User $user, \DateTimeInterface $idleSince): string
    {
        $id = (string) Str::uuid();
        Conversation::create(['id' => $id, 'user_id' => $user->id, 'title' => 'Test', 'created_at' => $idleSince, 'updated_at' => $idleSince]);
        ConversationMessage::create([
            'id' => (string) Str::uuid(),
            'conversation_id' => $id,
            'user_id' => $user->id,
            'agent' => InventoryAssistant::class,
            'role' => 'user',
            'content' => 'hi',
            'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
            'created_at' => $idleSince, 'updated_at' => $idleSince,
        ]);

        return $id;
    }

    public function test_it_forgets_conversations_idle_past_the_retention_window(): void
    {
        config(['ai.chat_retention_days' => 3]);
        $user = User::factory()->create();

        $stale = $this->seedConversation($user, now()->subDays(5)); // beyond 3-day window
        $fresh = $this->seedConversation($user, now()->subHours(2)); // recent

        $this->artisan('ai:forget-conversations')->assertSuccessful();

        $this->assertDatabaseMissing('agent_conversations', ['id' => $stale]);
        $this->assertDatabaseMissing('agent_conversation_messages', ['conversation_id' => $stale]); // cascaded
        $this->assertDatabaseHas('agent_conversations', ['id' => $fresh]); // kept
    }

    public function test_a_zero_window_disables_deletion(): void
    {
        config(['ai.chat_retention_days' => 0]);
        $user = User::factory()->create();
        $ancient = $this->seedConversation($user, now()->subYears(1));

        $this->artisan('ai:forget-conversations')->assertSuccessful();

        $this->assertDatabaseHas('agent_conversations', ['id' => $ancient]);
    }

    public function test_the_window_can_be_overridden_per_run(): void
    {
        config(['ai.chat_retention_days' => 30]); // would normally keep
        $user = User::factory()->create();
        $twoDaysOld = $this->seedConversation($user, now()->subDays(2));

        // --days=1 → 2-day-old thread is past the window and goes.
        $this->artisan('ai:forget-conversations', ['--days' => 1])->assertSuccessful();

        $this->assertDatabaseMissing('agent_conversations', ['id' => $twoDaysOld]);
    }
}
