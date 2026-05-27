<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\InventoryAssistant;
use App\Ai\Agents\ItemPhotoAnalyzer;
use App\Models\Item;
use App\Models\User;
use App\Services\Items\PendingItemImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertOk();
        $this->assertStringContainsString('Found 2 drills.', $response->json('reply'));

        $conversationId = $response->json('conversation_id');
        $this->assertNotNull($conversationId);

        $rehydrated = $this->actingAs($user)
            ->getJson('/assistant/conversation')
            ->assertOk()
            ->assertJsonPath('conversation_id', $conversationId)
            ->assertJsonFragment(['role' => 'user', 'content' => 'where are my drills?']);
        $this->assertStringContainsString('Found 2 drills.', collect($rehydrated->json('messages'))->firstWhere('role', 'assistant')['content']);

        InventoryAssistant::assertPrompted(fn () => true);
    }

    public function test_assistant_replies_are_rendered_as_sanitised_markdown(): void
    {
        InventoryAssistant::fake(["Found these:\n\n- **Drill** in the Garage\n- Saw\n\n<script>alert(1)</script>"]);

        $reply = $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'list my tools'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('<strong>Drill</strong>', $reply); // markdown rendered
        $this->assertStringContainsString('<li>', $reply);                    // list rendered
        $this->assertStringNotContainsString('<script>', $reply);             // raw HTML stripped
    }

    public function test_malformed_item_links_are_normalised(): void
    {
        // The model sometimes writes "[/items/557]" (url in the brackets, no label).
        $item = Item::factory()->create(['name' => 'Repaired Item']);
        InventoryAssistant::fake(["It is here ([/items/{$item->id}])."]);

        $reply = $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'x'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('href="/items/'.$item->id.'"', $reply); // became a real link
        $this->assertStringContainsString('Repaired Item', $reply);               // with the item name
        $this->assertStringNotContainsString('[/items/', $reply);                 // malformed form gone
    }

    public function test_item_links_to_missing_ids_are_stripped(): void
    {
        $item = Item::factory()->create(['name' => 'Real Item']);
        InventoryAssistant::fake(["See [Real Item](/items/{$item->id}) and [Ghost](/items/999999)."]);

        $reply = $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'x'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('href="/items/'.$item->id.'"', $reply); // real link kept
        $this->assertStringNotContainsString('/items/999999', $reply);            // bogus link removed
        $this->assertStringContainsString('Ghost', $reply);                       // ...degraded to text
    }

    public function test_a_wrong_id_is_self_healed_to_the_uniquely_named_item(): void
    {
        $item = Item::factory()->create(['name' => 'Unique Widget']);
        InventoryAssistant::fake(['Check [Unique Widget](/items/424242) — got the number wrong.']);

        $reply = $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'x'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('href="/items/'.$item->id.'"', $reply); // healed to the real id
        $this->assertStringNotContainsString('424242', $reply);                    // wrong id gone
    }

    public function test_unsafe_links_in_assistant_replies_are_neutralised(): void
    {
        InventoryAssistant::fake(['Try [bad](javascript:alert(1)) or [good](https://example.com).']);

        $reply = $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'hi'])
            ->assertOk()
            ->json('reply');

        $this->assertStringNotContainsString('javascript:', $reply);        // unsafe scheme dropped
        $this->assertStringContainsString('https://example.com', $reply);   // safe link kept
    }

    public function test_user_messages_are_returned_verbatim_not_as_markdown(): void
    {
        InventoryAssistant::fake(['ok']);
        $user = User::factory()->create();
        $raw = '**keep me literal** <b>x</b>';

        $this->actingAs($user)->postJson('/assistant/messages', ['message' => $raw])->assertOk();

        $userContent = collect($this->actingAs($user)->getJson('/assistant/conversation')->json('messages'))
            ->firstWhere('role', 'user')['content'];

        // User text is not run through Markdown — it round-trips exactly.
        $this->assertSame($raw, $userContent);
    }

    public function test_an_image_upload_still_works_when_no_details_are_detected(): void
    {
        Storage::fake('local');
        InventoryAssistant::fake(['I could not read details — describe it and I will add it.']);
        ItemPhotoAnalyzer::fake([[]]); // vision returns nothing usable
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/assistant/messages', [
                'message' => 'add this',
                'image' => UploadedFile::fake()->image('blurry.jpg', 300, 300),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        // The prompt notes the lack of detail, and the photo is still stashed for the create.
        InventoryAssistant::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'could not auto-detect'));
        $this->assertTrue(app(PendingItemImage::class)->has($response->json('conversation_id')));
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/assistant/messages', [
                'message' => 'x',
                'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
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

    public function test_an_uploaded_image_is_analysed_and_stashed_for_the_create(): void
    {
        Storage::fake('local');
        InventoryAssistant::fake(['I found a DeWalt drill in the photo. Shall I add it?']);
        ItemPhotoAnalyzer::fake([['name' => 'DeWalt Drill', 'manufacturer' => 'DeWalt']]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post('/assistant/messages', [
                'message' => 'add this please',
                'image' => UploadedFile::fake()->image('drill.jpg', 300, 300),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $conversationId = $response->json('conversation_id');

        // The vision-detected fields were folded into the prompt the chat model saw.
        InventoryAssistant::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'DeWalt'));

        // The downscaled photo is stashed for the upcoming confirmation turn.
        $this->assertTrue(app(PendingItemImage::class)->has($conversationId));
    }

    public function test_a_message_with_only_an_image_is_accepted(): void
    {
        Storage::fake('local');
        InventoryAssistant::fake(['ok']);
        ItemPhotoAnalyzer::fake([['name' => 'Mug']]);

        $this->actingAs(User::factory()->create())
            ->post('/assistant/messages', ['image' => UploadedFile::fake()->image('mug.jpg', 200, 200)], ['Accept' => 'application/json'])
            ->assertOk();

        InventoryAssistant::assertPrompted(fn () => true);
    }

    public function test_the_current_item_is_passed_to_the_agent_as_context(): void
    {
        $item = Item::factory()->create(['name' => 'Espresso Machine']);
        InventoryAssistant::fake(['It belongs in the kitchen.']);

        $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'where should I store this?', 'context_item_id' => $item->id])
            ->assertOk();

        // The viewed item is woven into the agent's instructions (not the stored message).
        InventoryAssistant::assertPrompted(function ($prompt) use ($item): bool {
            return str_contains($prompt->agent->instructions(), 'currently viewing')
                && str_contains($prompt->agent->instructions(), "/items/{$item->id}");
        });
    }

    public function test_no_item_context_is_added_without_a_context_id(): void
    {
        InventoryAssistant::fake(['Hi.']);

        $this->actingAs(User::factory()->create())
            ->postJson('/assistant/messages', ['message' => 'hello'])
            ->assertOk();

        InventoryAssistant::assertPrompted(fn ($prompt) => ! str_contains($prompt->agent->instructions(), 'currently viewing'));
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
