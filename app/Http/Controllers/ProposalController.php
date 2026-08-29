<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ItemProposal;
use App\Services\Items\ItemWriter;
use App\Services\Proposals\ProposalPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The review queue for AI-derived suggestions.
 *
 * Accepting one is an ordinary item edit — it goes through ItemWriter so the
 * change is normalised, re-indexed for search and audited exactly like a change
 * made by hand. That matters here more than usual: the reason for describing a
 * box's contents at all is to make it findable, which only happens on reindex.
 *
 * Not admin-gated. Accepting edits an item, and members already edit items;
 * restricting this would be stricter than the thing it stands in for.
 */
class ProposalController extends Controller
{
    public function __construct(private readonly ProposalPresenter $presenter) {}

    public function index(): Response
    {
        $proposals = ItemProposal::pending()
            ->with(['item:id,name,parent_id,description,manufacturer,model_number,serial_number', 'item.images'])
            ->oldest()
            ->get()
            ->map($this->presenter->present(...))
            // Grouped by item, because that is the unit a person reviews: the
            // photos and the context are the item's, and two suggestions about
            // the same box are one decision to sit down to, not two.
            ->groupBy('item.id')
            ->map(fn ($group): array => [
                'item' => $group->first()['item'],
                'suggestions' => $group->values(),
            ])
            ->values();

        return Inertia::render('Proposals', [
            'proposals' => $proposals,
        ]);
    }

    public function accept(Request $request, ItemProposal $proposal, ItemWriter $writer): RedirectResponse
    {
        $this->assertPending($proposal);

        // Someone edited the item while this sat in the queue. Applying it now
        // would silently undo their edit, so make them look again instead.
        if ($proposal->isStale()) {
            throw ValidationException::withMessages([
                'proposal' => __('proposals.stale'),
            ]);
        }

        $writer->update($proposal->item, [$proposal->field->value => $proposal->proposed_value]);

        $proposal->markAccepted($request->user());

        return back();
    }

    public function reject(Request $request, ItemProposal $proposal): RedirectResponse
    {
        $this->assertPending($proposal);

        $proposal->markRejected($request->user());

        return back();
    }

    /**
     * Two people reviewing the same queue is the ordinary case in a shared
     * household, and Inertia reserves 409, so this is a validation error.
     */
    private function assertPending(ItemProposal $proposal): void
    {
        if (! $proposal->isPending()) {
            throw ValidationException::withMessages([
                'proposal' => __('proposals.already_reviewed'),
            ]);
        }
    }
}
