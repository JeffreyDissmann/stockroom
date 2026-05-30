<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Manages an item's links to Paperless-ngx documents (#7). v1 ships only
 * the destroy action — links are created by the intake job from a webhook,
 * not by the user. Adding linkable docs from the Stockroom UI would need
 * a Paperless-side search which we deliberately don't expose.
 */
class PaperlessLinkController extends Controller
{
    public function destroy(Item $item, int $document): RedirectResponse
    {
        // Local pivot row goes first — that's the durable record on our
        // side. If the Paperless API call below fails, the item's Show
        // page still correctly shows the doc as unlinked.
        $item->paperlessLinks()
            ->where('paperless_document_id', $document)
            ->delete();

        $this->clearItemIdOnPaperless($document, $item->id);

        return back();
    }

    /**
     * Rewrite the Paperless doc's `stockroom_item_ids` custom field to drop
     * the unlinked item's id. The field is a comma-separated list (one doc
     * may map to many items); we filter, rejoin and write back. Silent log
     * on Paperless API errors so a transient outage doesn't block the
     * user's local unlink.
     */
    private function clearItemIdOnPaperless(int $documentId, int $itemId): void
    {
        $client = PaperlessClient::fromConfig();
        if ($client === null) {
            return;
        }

        $field = (string) config('paperless.link_custom_field');

        try {
            $current = $client->getCustomField($documentId, $field);
            $remaining = collect(explode(',', (string) $current))
                ->map(fn ($id) => trim((string) $id))
                ->filter(fn ($id) => $id !== '' && $id !== (string) $itemId)
                ->values()
                ->implode(',');

            $client->setCustomField($documentId, $field, $remaining === '' ? null : $remaining);
        } catch (PaperlessException $e) {
            Log::warning('paperless.unlink.annotate_failed', [
                'document_id' => $documentId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
