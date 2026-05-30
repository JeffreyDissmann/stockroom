<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\RedirectResponse;

/**
 * Manages an item's links to Paperless-ngx documents (#7). v1 ships only
 * the destroy action — links are created by the intake job from a webhook,
 * not by the user.
 *
 * Paperless's side of the link is a URL custom field pointing to
 * Stockroom's search page filtered to this doc's items. It's set once on
 * intake and never rewritten, so unlinking a single item is a pure local
 * operation — no Paperless API round-trip, no error path to handle. If the
 * user unlinks every item the doc had, the URL still resolves to a valid
 * (empty) search page.
 */
class PaperlessLinkController extends Controller
{
    public function destroy(Item $item, int $document): RedirectResponse
    {
        $item->paperlessLinks()
            ->where('paperless_document_id', $document)
            ->delete();

        return back();
    }
}
