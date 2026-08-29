<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Models\ItemProposal;

/**
 * Shapes a proposal for the review page.
 *
 * Sits alongside MaintenancePresenter and BatteryPresenter: anything the UI
 * needs computed — the stale check, the human-readable field name — is worked
 * out once here rather than reimplemented in TypeScript, where it would drift
 * from the rules the accept path actually enforces.
 */
class ProposalPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(ItemProposal $proposal): array
    {
        return [
            'id' => $proposal->id,
            'field' => $proposal->field->value,
            'field_label' => $proposal->field->label(),
            'current_value' => $proposal->current_value,
            'proposed_value' => $proposal->proposed_value,
            'photo_count' => count($proposal->source_image_ids),
            'model' => $proposal->model,
            'created_at_human' => $proposal->created_at?->diffForHumans(),
            // Server-computed on purpose: the accept path refuses a stale
            // proposal, so the badge warning about it must come from the same
            // rule rather than a guess made in the browser.
            'is_stale' => $proposal->isStale(),
            'item' => [
                'id' => $proposal->item->id,
                'name' => $proposal->item->name,
                // Every photo, not just the primary one: these suggestions are
                // read *from* the photos, so judging one against a single
                // thumbnail means judging most of the evidence unseen.
                'images' => $proposal->item->images
                    ->map(fn ($image): array => [
                        'id' => $image->id,
                        'thumb_url' => $image->thumbUrl(),
                    ])
                    ->values()
                    ->all(),
                'location_path' => $proposal->item->locationPath(),
            ],
        ];
    }
}
