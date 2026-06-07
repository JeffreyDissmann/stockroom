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

        $item->paperlessLinks()->updateOrCreate(
            ['paperless_document_id' => $documentId],
            $this->metadataFromDocument($document),
        );

        $this->annotate($documentId);

        return (string) ($document['title'] ?? "Document {$documentId}");
    }

    /**
     * The cached display metadata for a link row, from a full document
     * payload: title verbatim, type/correspondent resolved from their ids
     * to names. Resolution is best-effort — a lookup hiccup yields nulls
     * (the repair job re-derives them later) rather than failing the write
     * that asked. Shared by the manual-link path, the intake job and the
     * repair job so all link rows carry the same shape.
     *
     * @param  array<string, mixed>  $document
     * @return array{document_title: ?string, document_type: ?string, correspondent: ?string}
     */
    public function metadataFromDocument(array $document): array
    {
        $title = $document['title'] ?? null;

        try {
            $type = $this->client->documentTypeName(is_numeric($document['document_type'] ?? null) ? (int) $document['document_type'] : null);
            $correspondent = $this->client->correspondentName(is_numeric($document['correspondent'] ?? null) ? (int) $document['correspondent'] : null);
        } catch (PaperlessException) {
            $type = null;
            $correspondent = null;
        }

        return [
            'document_title' => is_string($title) && trim($title) !== '' ? mb_substr(trim($title), 0, 255) : null,
            'document_type' => $type !== null ? mb_substr($type, 0, 255) : null,
            'correspondent' => $correspondent !== null ? mb_substr($correspondent, 0, 255) : null,
        ];
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
