<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Models\Item;
use App\Services\Paperless\PaperlessException;
use App\Services\Paperless\PaperlessLinker;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Assistant-side twin of PaperlessLinkController::store(): link a Paperless
 * document to an item by id or URL. Only registered on the agent when the
 * Paperless integration is configured — resolving PaperlessLinker without
 * it would throw (see the container binding in AppServiceProvider).
 */
class LinkPaperlessDocument implements Tool
{
    use FormatsItemLinks;

    public function __construct(private readonly PaperlessLinker $linker) {}

    public function description(): string
    {
        return 'Link a Paperless-ngx document (a receipt, manual, invoice…) to an inventory item. '
            .'The document is referenced by its numeric id or its Paperless URL. The document is '
            .'verified against Paperless before linking. Always confirm with the user before calling this.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_id' => $schema->integer()->description('Id of the item the document belongs to.')->required(),
            'document' => $schema->string()->description('The Paperless document: a numeric id ("447") or a document URL.')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['item_id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $documentId = PaperlessLinker::parseDocumentReference((string) ($request['document'] ?? ''));

        if ($documentId === null) {
            return 'The document must be referenced by a numeric id or a Paperless document URL.';
        }

        try {
            $title = $this->linker->link($item, $documentId);
        } catch (PaperlessException) {
            return "Document {$documentId} could not be verified in Paperless — check the id with the user.";
        }

        // The title is admin-only: members may link docs they explicitly
        // reference, but only existence is confirmed — describing content
        // by id would amount to slow-motion browsing, which (like search)
        // is reserved for household admins.
        $titled = auth()->user()?->is_admin ? " (\"{$title}\")" : '';

        return "Linked Paperless document #{$documentId}{$titled} to {$this->itemLink($item)}.";
    }
}
