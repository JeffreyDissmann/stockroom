<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\InventoryAssistant;
use App\Http\Middleware\EnsureAiEnabled;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Messages\MessageRole;
use Throwable;

/**
 * Chat endpoint for the inventory assistant. Runs the InventoryAssistant agent
 * with the SDK's conversation memory so threads persist per user.
 */
#[Middleware(EnsureAiEnabled::class)]
class AssistantController extends Controller
{
    public function __construct(private readonly ConversationStore $conversations) {}

    public function messages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $agent = new InventoryAssistant;

        // Only continue a thread the user actually owns; otherwise start fresh.
        if (! empty($validated['conversation_id']) && $user->conversations()->whereKey($validated['conversation_id'])->exists()) {
            $agent->continue($validated['conversation_id'], $user);
        } else {
            $agent->forUser($user);
        }

        try {
            $response = $agent->prompt($validated['message'], model: config('ai.chat_model'), timeout: 120);
        } catch (Throwable $e) {
            report($e);
            abort(502, 'The assistant is unavailable right now. Please try again.');
        }

        return response()->json([
            'conversation_id' => $agent->currentConversation(),
            'reply' => $response->text,
        ]);
    }

    /**
     * The user's most recent conversation, to rehydrate the panel on open.
     */
    public function conversation(Request $request): JsonResponse
    {
        $id = $this->resumableConversationId($request->user());

        $messages = $id === null ? [] : collect($this->conversations->getLatestConversationMessages($id, 100))
            ->filter(fn ($m): bool => in_array($m->role, [MessageRole::User, MessageRole::Assistant], true) && trim((string) $m->content) !== '')
            ->map(fn ($m): array => ['role' => $m->role->value, 'content' => (string) $m->content])
            ->values()
            ->all();

        return response()->json(['conversation_id' => $id, 'messages' => $messages]);
    }

    /**
     * The conversation to resume on open: the user's most recent thread, unless
     * it has been idle longer than the configured reset window — then the panel
     * starts fresh. A window of 0 hours disables the timeout (always resume).
     */
    private function resumableConversationId(User $user): ?string
    {
        $latest = $user->conversations()->latest('updated_at')->first();

        if ($latest === null) {
            return null;
        }

        $hours = (int) config('ai.chat_reset_after_hours', 3);

        if ($hours > 0 && $latest->updated_at->lt(now()->subHours($hours))) {
            return null;
        }

        return $latest->id;
    }
}
