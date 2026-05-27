<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\InventoryAssistant;
use App\Ai\Agents\ItemPhotoAnalyzer;
use App\Ai\AssistantContext;
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
use Illuminate\Support\Str;
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

        return response()->json([
            'conversation_id' => $conversationId,
            'reply' => $this->renderAssistantMarkdown($response->text),
        ]);
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
                    ? $this->renderAssistantMarkdown((string) $m->content)
                    : (string) $m->content,
            ])
            ->values()
            ->all();

        return response()->json(['conversation_id' => $id, 'messages' => $messages]);
    }

    /**
     * Render an assistant Markdown reply to HTML, stripping any raw HTML and
     * unsafe links so the result is safe to inject with v-html on the client.
     * Item links the model produced are validated against real ids.
     */
    private function renderAssistantMarkdown(string $text): string
    {
        return $this->validateItemLinks(Str::markdown($this->normaliseItemLinks($text), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /**
     * Repair a common model mistake — writing the URL inside the brackets with
     * no label, e.g. "[/items/557]" — into a proper [Name](/items/557) link so
     * it renders instead of showing as literal text.
     */
    private function normaliseItemLinks(string $text): string
    {
        return preg_replace_callback('#\[/items/(\d+)\](?!\()#', function (array $m): string {
            $item = Item::find((int) $m[1]);

            if ($item === null) {
                return $m[0];
            }

            $label = str_replace(['[', ']'], ['\[', '\]'], $item->name);

            return "[{$label}](/items/{$item->id})";
        }, $text) ?? $text;
    }

    /**
     * Fix up /items/{id} links the model wrote. A link to a real id is kept; a
     * link to a missing id is self-healed when its text uniquely names a real
     * item (the model likely got the number wrong), otherwise it degrades to
     * plain text — so a hallucinated id can never become a 404 link.
     */
    private function validateItemLinks(string $html): string
    {
        preg_match_all('#<a\b[^>]*\bhref="/items/(\d+)"[^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return $html;
        }

        $existing = Item::whereIn('id', array_unique(array_map(static fn (array $m): int => (int) $m[1], $matches)))
            ->pluck('id')
            ->flip();

        foreach ($matches as [$anchor, $id, $label]) {
            if ($existing->has((int) $id)) {
                continue;
            }

            $healedId = $this->itemIdForLabel($label);

            $html = str_replace(
                $anchor,
                $healedId !== null ? str_replace("/items/{$id}", "/items/{$healedId}", $anchor) : $label,
                $html,
            );
        }

        return $html;
    }

    /**
     * Resolve a link's visible text to a real item id, but only when exactly one
     * item bears that name (an ambiguous or unknown name can't be healed safely).
     */
    private function itemIdForLabel(string $label): ?int
    {
        $name = trim(html_entity_decode(strip_tags($label), ENT_QUOTES));

        if ($name === '') {
            return null;
        }

        // Case-insensitive exact match (no wildcards added); heal only when unambiguous.
        $ids = Item::whereLike('name', $name, caseSensitive: false)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
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
