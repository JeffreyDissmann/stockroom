<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Models\Item;
use App\Models\PaperlessLink;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Read a linked Paperless document's OCR text so the assistant can answer
 * questions from receipts, manuals and invoices ("what does the receipt say
 * about the warranty?").
 *
 * Scope follows the linked-document precedent: only documents already
 * linked to an inventory item are readable — linked docs are household-
 * visible by definition, while DISCOVERING unlinked documents (search,
 * content probing by id) stays admin-only. An unlinked id is reported
 * exactly like a nonexistent one. Only registered while Paperless is
 * configured (see InventoryAssistant::tools()).
 */
class GetPaperlessDocument implements Tool
{
    use FormatsItemLinks;

    /**
     * OCR text cap (characters). Receipts run 1-5k; this keeps a 40-page
     * manual from flooding the model's context while leaving the parts
     * that answer most questions (front matter, spec tables) intact.
     */
    private const int CONTENT_LIMIT = 10_000;

    public function __construct(private readonly PaperlessClient $client) {}

    public function description(): string
    {
        return 'Read the text content of a Paperless document that is linked to an inventory item '
            .'(receipts, manuals, invoices) — use it to answer questions about the document, e.g. '
            .'warranty terms or what was bought. Get the document id from get_item. Only linked '
            .'documents are readable.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'document_id' => $schema->integer()->description('The Paperless document id (as shown by get_item).')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $documentId = (int) ($request['document_id'] ?? 0);

        $links = PaperlessLink::query()
            ->where('paperless_document_id', $documentId)
            ->with('item')
            ->get();

        if ($links->isEmpty()) {
            return 'No linked document with that id. Only documents linked to an item can be read — check get_item for the item\'s documents.';
        }

        try {
            $document = $this->client->document($documentId);
        } catch (PaperlessException) {
            return "Document {$documentId} could not be fetched from Paperless right now.";
        }

        $items = $links->map(fn (PaperlessLink $link): string => $this->itemLink($link->item))->implode(', ');

        $lines = [
            "Paperless document #{$documentId} \"{$document['title']}\"",
            "Linked to: {$items}",
            '',
            $this->cappedContent((string) ($document['content'] ?? '')),
        ];

        return implode("\n", $lines);
    }

    private function cappedContent(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '(The document has no readable text.)';
        }

        if (mb_strlen($content) <= self::CONTENT_LIMIT) {
            return $content;
        }

        return mb_substr($content, 0, self::CONTENT_LIMIT)
            ."\n\n(Truncated — the document is longer; this is the first ".self::CONTENT_LIMIT.' characters.)';
    }
}
