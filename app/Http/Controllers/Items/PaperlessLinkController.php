<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Http\Requests\Item\StorePaperlessLinkRequest;
use App\Models\Item;
use App\Services\Paperless\PaperlessClient;
use App\Services\Paperless\PaperlessException;
use App\Services\Paperless\PaperlessLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Manages an item's links to Paperless-ngx documents (#7). Links are
 * created by the intake job from a webhook, or by hand from the item's
 * Connections card (store).
 *
 * Paperless's side of the link is a URL custom field pointing to
 * Stockroom's search page filtered to this doc's items. It's set once on
 * intake (or manual link) and never rewritten, so unlinking a single item
 * is a pure local operation — no Paperless API round-trip, no error path
 * to handle. If the user unlinks every item the doc had, the URL still
 * resolves to a valid (empty) search page.
 */
class PaperlessLinkController extends Controller
{
    /**
     * JSON picker data for the manual-link search box: documents matching
     * the query (or the most recent ones when it's empty), as id/title
     * pairs. Admin-only at the route — Paperless's per-user permissions
     * can't be mirrored, so free search over the service token is reserved
     * for household admins; members link by id/URL instead. A Paperless
     * hiccup surfaces as 502 so the picker can say "unreachable" instead of
     * silently rendering an empty list.
     *
     * The client comes from the container binding (see AppServiceProvider);
     * EnsurePaperlessEnabled on the route guarantees it resolves.
     */
    public function search(Request $request, PaperlessClient $client): JsonResponse
    {
        try {
            $documents = $client->searchDocuments((string) $request->query('q', ''));
        } catch (PaperlessException) {
            abort(502);
        }

        return response()->json(['documents' => $documents]);
    }

    /**
     * Link a document the user referenced by id or pasted URL. The linker
     * verifies it against Paperless first — an unknown id (or an
     * unreachable Paperless) surfaces as a validation error on the input
     * rather than creating a link we can't vouch for.
     */
    public function store(StorePaperlessLinkRequest $request, Item $item, PaperlessLinker $linker): RedirectResponse
    {
        try {
            $linker->link($item, $request->documentId());
        } catch (PaperlessException) {
            throw ValidationException::withMessages([
                'document' => __('validation.custom.paperless_document.unverified'),
            ]);
        }

        return back();
    }

    public function destroy(Item $item, int $document): RedirectResponse
    {
        $item->paperlessLinks()
            ->where('paperless_document_id', $document)
            ->delete();

        return back();
    }
}
