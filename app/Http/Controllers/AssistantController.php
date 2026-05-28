<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\InventoryAssistant;
use App\Ai\Agents\ItemPhotoAnalyzer;
use App\Ai\AssistantContext;
use App\Ai\ReplyPresenter;
use App\Http\Middleware\EnsureAiEnabled;
use App\Models\Item;
use App\Models\User;
use App\Services\ItemImageProcessor;
use App\Services\Items\PendingItemImage;
use ArrayAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\MessageRole;
use Throwable;

/**
 * Chat endpoint for the inventory assistant. Runs the InventoryAssistant agent
 * with the SDK's conversation memory so threads persist per user.
 */
#[Middleware(EnsureAiEnabled::class)]
class AssistantController extends Controller
{
    public function __construct(
        private readonly ConversationStore $conversations,
        private readonly ItemImageProcessor $images,
        private readonly PendingItemImage $pendingImage,
        private readonly AssistantContext $context,
        private readonly ReplyPresenter $presenter,
    ) {}

    public function messages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required_without:image', 'nullable', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'string'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:10240', 'dimensions:min_width=64,min_height=64'],
            // Id of the item whose page the chat was opened from (ambient context).
            'context_item_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $agent = new InventoryAssistant;

        // Only continue a thread the user actually owns; otherwise start fresh.
        $resume = ! empty($validated['conversation_id']) && $user->conversations()->whereKey($validated['conversation_id'])->exists();

        if ($resume) {
            $agent->continue($validated['conversation_id'], $user);
        } else {
            $agent->forUser($user);
        }

        // Tools resolve this to know which thread they're in (to attach a photo).
        $this->context->conversationId = $resume ? $validated['conversation_id'] : null;

        // If opened from an item page, make that item ambient context (resolves "this"/"it").
        if (! empty($validated['context_item_id'])) {
            $agent->aboutItem(Item::find((int) $validated['context_item_id']));
        }

        $message = trim((string) ($validated['message'] ?? ''));
        $stashedImage = null;

        if ($request->hasFile('image')) {
            [$message, $stashedImage] = $this->describeUploadedImage($request->file('image'), $message);

            // Continuing a known thread: stash now so the model can attach the
            // photo if it creates the item within this same turn.
            if ($resume) {
                $this->pendingImage->put($validated['conversation_id'], $stashedImage);
            }
        }

        try {
            $response = $agent->prompt($message, model: config('ai.chat_model'), timeout: 120);
        } catch (Throwable $e) {
            report($e);
            abort(502, 'The assistant is unavailable right now. Please try again.');
        }

        $conversationId = $agent->currentConversation();
        $this->context->conversationId ??= $conversationId;

        // New thread: the id only exists after prompting, so stash for the next
        // (confirmation) turn, when the model will call create_item.
        if (! $resume && $stashedImage !== null && $conversationId !== null) {
            $this->pendingImage->put($conversationId, $stashedImage);
        }

        [$mutated, $redirectTo] = $this->detectMutations(
            $response->toolCalls ?? [],
            ! empty($validated['context_item_id']) ? (int) $validated['context_item_id'] : null,
        );

        return response()->json([
            'conversation_id' => $conversationId,
            'reply' => $this->presenter->render($response->text),
            'mutated' => $mutated,
            'redirect_to' => $redirectTo,
        ]);
    }

    /**
     * Inspect the turn's tool calls so the panel can refresh stale page data:
     * any write tool → mutated=true; deleting the currently viewed item →
     * redirect_to /items, since the page about to be reloaded is gone.
     *
     * @return array{0: bool, 1: ?string}
     */
    private function detectMutations(iterable $toolCalls, ?int $contextItemId): array
    {
        $writeTools = ['CreateItem', 'UpdateItem', 'MoveItem', 'AssignTags', 'DeleteItem'];
        $mutated = false;
        $redirectTo = null;

        foreach ($toolCalls as $call) {
            $name = is_object($call) ? ($call->name ?? '') : ($call['name'] ?? '');

            if (! in_array($name, $writeTools, true)) {
                continue;
            }

            $mutated = true;

            $arguments = is_object($call) ? ($call->arguments ?? []) : ($call['arguments'] ?? []);

            if ($name === 'DeleteItem' && $contextItemId !== null && (int) ($arguments['id'] ?? 0) === $contextItemId) {
                $redirectTo = '/items';
            }
        }

        return [$mutated, $redirectTo];
    }

    /**
     * Run an uploaded photo through the vision agent, fold the detected fields
     * into the chat message (so the text-only chat model can act on them), and
     * return [augmented message, downscaled JPEG to stash for the create].
     *
     * @return array{0: string, 1: string}
     */
    private function describeUploadedImage(UploadedFile $image, string $message): array
    {
        $language = config('app.supported_locales.'.app()->getLocale().'.ai', 'English');
        $jpeg = $this->images->downscaleToJpeg($image, 1280, 80);

        $detected = [];

        try {
            $analysis = (new ItemPhotoAnalyzer($language))->prompt(
                'Catalogue the main item shown in this photo.',
                attachments: [Image::fromBase64(base64_encode($jpeg), 'image/jpeg')],
                model: config('ai.vision_model'),
                timeout: 120,
            );

            if ($analysis instanceof ArrayAccess) {
                foreach (['name', 'manufacturer', 'model_number', 'serial_number', 'description'] as $key) {
                    $value = $analysis[$key] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        $detected[$key] = trim($value);
                    }
                }
            }
        } catch (Throwable) {
            // Vision analysis is best-effort; fall back to just attaching the photo.
        }

        if ($message === '') {
            $message = 'Create a new inventory item from the photo I just uploaded.';
        }

        $summary = $detected === []
            ? 'could not auto-detect details from it'
            : 'detected '.collect($detected)->map(fn (string $value, string $key): string => "{$key}=\"{$value}\"")->implode(', ');

        return [
            $message
                ."\n\n[The user attached a photo of an item; the vision model {$summary}. "
                ."The photo will be saved as the new item's photo when you create it. "
                .'Propose creating this item, then confirm with the user before calling create_item.]',
            $jpeg,
        ];
    }

    /**
     * The user's most recent conversation, to rehydrate the panel on open.
     */
    public function conversation(Request $request): JsonResponse
    {
        $id = $this->resumableConversationId($request->user());

        $messages = $id === null ? [] : collect($this->conversations->getLatestConversationMessages($id, 100))
            ->filter(fn ($m): bool => in_array($m->role, [MessageRole::User, MessageRole::Assistant], true) && trim((string) $m->content) !== '')
            ->map(fn ($m): array => [
                'role' => $m->role->value,
                // Assistant replies are Markdown → render to safe HTML; user text stays verbatim.
                'content' => $m->role === MessageRole::Assistant
                    ? $this->presenter->render((string) $m->content)
                    : (string) $m->content,
            ])
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
