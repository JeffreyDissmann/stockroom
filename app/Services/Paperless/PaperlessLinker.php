<?php

declare(strict_types=1);

namespace App\Services\Paperless;

use App\Models\Item;
use App\Models\PaperlessLink;
use Illuminate\Support\Facades\Log;

/**
 * Creates Paperless document links by hand — the user-initiated counterpart
 * to the webhook intake job. Verifies the document exists before linking, so
 * a typo'd id becomes a friendly error instead of a dead link; then mirrors
 * intake's Paperless-side annotation best-effort — the local link is already
 * committed, an unreachable Paperless never rolls it back.
 */
class PaperlessLinker
{
    /**
     * Container-injected (see AppServiceProvider): resolving the linker
     * while the integration is unconfigured throws — callers behind
     * EnsurePaperlessEnabled never hit that, and anything else shouldn't
     * be linking documents in the first place.
     */
    public function __construct(private readonly PaperlessClient $client) {}

    /**
     * Extract a Paperless document id from user input: a bare integer or any
     * URL containing /documents/{id}, with or without the trailing slash —
     * the same forms paperless:adopt-custom-field accepts.
     */
    public static function parseDocumentReference(string $raw): ?int
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (ctype_digit($raw)) {
            return (int) $raw;
        }

        if (preg_match('#/documents/(\d+)/?#', $raw, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Verify the document exists, link it to the item (idempotently — re-linking
     * an already-linked doc is a no-op) and annotate the Paperless side. Returns
     * the document's title for confirmation messages.
     *
     * @throws PaperlessException when the document cannot be verified
     */
    public function link(Item $item, int $documentId): string
    {
        $document = $this->client->document($documentId);

        $item->paperlessLinks()->firstOrCreate(['paperless_document_id' => $documentId]);

        $this->annotate($documentId);

        return (string) ($document['title'] ?? "Document {$documentId}");
    }

    /**
     * The same single-PATCH annotation as intake (trigger→linked tag swap plus
     * the backlink custom field) with the same failure policy: log and move on.
     * The "Repair Paperless links" job re-applies it later if this attempt
     * failed.
     */
    private function annotate(int $documentId): void
    {
        try {
            $this->client->annotateProcessed(
                $documentId,
                (string) config('paperless.trigger_tag'),
                (string) config('paperless.linked_tag'),
                (string) config('paperless.link_custom_field'),
                PaperlessLink::stockroomBacklinkFor($documentId),
            );
        } catch (PaperlessException $e) {
            Log::warning('paperless.manual_link.annotate_failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
