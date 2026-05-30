<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\DocumentExtractor;
use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;
use App\Services\Brave\AttachFirstImage;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes a Paperless-ngx webhook intake.
 *
 * Main path: fetch the doc → hand the OCR text to the DocumentExtractor
 * agent → for each item it returns, create a Stockroom item and link it
 * to the doc → annotate the Paperless side (tag swap + back-reference
 * custom field) so the workflow doesn't re-fire.
 *
 * Fallback: when the agent returns zero items (or fails outright), a
 * single placeholder item is created using the doc title. The Paperless
 * annotation still happens, so re-trigger requires deliberately re-tagging.
 *
 * Tries(1) — Paperless retries are user-driven (re-tag), so we don't
 * silently retry on failure: a manual fix-up beats accidentally creating
 * the same items twice.
 */
#[Tries(1)]
class ProcessPaperlessDocumentJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;

    public function __construct(public readonly int $documentId) {}

    public function handle(PaperlessClient $client): void
    {
        $doc = $client->document($this->documentId);
        $title = (string) ($doc['title'] ?? "Paperless doc {$this->documentId}");
        $ocr = (string) ($doc['content'] ?? '');

        $extracted = $this->extractItems($ocr);

        $items = DB::transaction(function () use ($extracted, $title): array {
            if ($extracted === []) {
                // Placeholder fallback when the AI returned nothing — at
                // least one item per doc so the user has a hook to find it.
                return [$this->createItem(['name' => $title])];
            }

            return array_map(fn (array $proposal) => $this->createItem($proposal), $extracted);
        });

        // Opportunistic auto-cover: when Brave is configured, search the
        // web for an image of each newly-created item and attach the first
        // hit. Runs outside the transaction (image writes touch disk) and
        // swallows its own failures — a missing cover is no reason to
        // strand a successful intake.
        $attacher = app(AttachFirstImage::class);
        foreach ($items as $item) {
            $attacher($item);
        }

        $this->annotatePaperlessDocument($client);
    }

    /**
     * Hand the OCR text to the DocumentExtractor agent and unwrap the
     * structured `items` array. Returns [] on any failure so the caller
     * can fall back to the placeholder path — extraction quality on
     * messy docs is variable; never blocking item creation entirely is
     * a better UX than silently dropping a tagged doc.
     *
     * @return list<array<string, mixed>>
     */
    private function extractItems(string $ocr): array
    {
        if (! (bool) config('ai.enabled') || trim($ocr) === '') {
            return [];
        }

        // Reuse the existing locale → AI language mapping in
        // config('app.supported_locales'). New locales added there (each
        // entry has an 'ai' key like 'German' / 'Spanish') are picked up
        // automatically by both photo analysis and document extraction.
        // Falls back to English for unmapped locales.
        //
        // Uses the household default (config('app.locale')) rather than
        // app()->getLocale() because this job runs from a webhook with no
        // per-user request context.
        $defaultLocale = (string) config('app.locale', 'en');
        $language = (string) config("app.supported_locales.{$defaultLocale}.ai", 'English');

        $prompt = <<<PROMPT
            Extract the inventory-worthy items mentioned in this document.

            OCR TEXT:
            {$ocr}
            PROMPT;

        try {
            $result = (new DocumentExtractor($language))->prompt(
                $prompt,
                model: config('ai.chat_model'),
            );
        } catch (Throwable $e) {
            Log::warning('paperless.intake.extraction_failed', [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        // AgentResponse implements ArrayAccess, so `$result['items']` works
        // either when prompt() returns the live response or when tests inject
        // a plain array via the Promptable fake.
        $items = $result['items'] ?? [];

        // Defensive — drop anything without a usable name. The schema
        // marks it required, but small Ollama models occasionally return
        // null fields that survive validation.
        return array_values(array_filter(
            $items,
            fn ($i) => is_array($i) && is_string($i['name'] ?? null) && trim($i['name']) !== '',
        ));
    }

    /**
     * Create a single Stockroom item from an extraction proposal, plus
     * the PaperlessLink back-reference. Field whitelist mirrors what
     * DocumentExtractor's schema can produce.
     *
     * @param  array<string, mixed>  $proposal
     */
    private function createItem(array $proposal): Item
    {
        $item = Item::create([
            'type' => ItemType::Item->value,
            // Drop new items into the room / container the admin configured
            // in household preferences. Null = top-level, which is also
            // what happens if the preference points at a since-deleted item
            // (the deletion guard in ItemController makes that unlikely,
            // but a stale id from a backup restore is possible).
            'parent_id' => Setting::int('paperless_parent_id'),
            'name' => (string) $proposal['name'],
            'description' => isset($proposal['description']) && is_string($proposal['description'])
                ? $proposal['description']
                : null,
            'manufacturer' => isset($proposal['manufacturer']) && is_string($proposal['manufacturer'])
                ? $proposal['manufacturer']
                : null,
            'model_number' => isset($proposal['model_number']) && is_string($proposal['model_number'])
                ? $proposal['model_number']
                : null,
            'serial_number' => isset($proposal['serial_number']) && is_string($proposal['serial_number'])
                ? $proposal['serial_number']
                : null,
            'purchase_price' => isset($proposal['purchase_price']) && is_numeric($proposal['purchase_price'])
                ? (string) $proposal['purchase_price']
                : null,
            'purchase_date' => isset($proposal['purchase_date']) && is_string($proposal['purchase_date'])
                ? $proposal['purchase_date']
                : null,
            'quantity' => is_int($proposal['quantity'] ?? null) && $proposal['quantity'] > 0
                ? $proposal['quantity']
                : 1,
        ]);

        $item->paperlessLinks()->create([
            'paperless_document_id' => $this->documentId,
        ]);

        return $item;
    }

    /**
     * Write back to Paperless: a stable URL backlink into the link custom
     * field, plus the trigger→linked tag swap. The URL points at the
     * Stockroom search page filtered to items linked to this doc, so it
     * stays valid even after the user unlinks items locally. Idempotent —
     * re-running the job rewrites the same URL.
     *
     * Done as one combined PATCH (see PaperlessClient::annotateProcessed):
     * separate calls would each fire DOCUMENT_UPDATED, and the first one
     * (custom-field write) would re-trigger this very workflow while the
     * doc still carried the trigger tag — duplicate intake on every run.
     *
     * Local DB state is already committed by this point — a Paperless API
     * hiccup here is logged but doesn't roll back the items.
     */
    private function annotatePaperlessDocument(PaperlessClient $client): void
    {
        $linkField = (string) config('paperless.link_custom_field');
        $triggerTag = (string) config('paperless.trigger_tag');
        $linkedTag = (string) config('paperless.linked_tag');
        $backlink = rtrim((string) config('app.url'), '/').'/search?paperless_document='.$this->documentId;

        try {
            $client->annotateProcessed($this->documentId, $triggerTag, $linkedTag, $linkField, $backlink);
        } catch (PaperlessException $e) {
            Log::warning('paperless.intake.annotate_failed', [
                'document_id' => $this->documentId,
                'backlink' => $backlink,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
