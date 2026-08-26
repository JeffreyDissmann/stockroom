<?php

declare(strict_types=1);

namespace App\Services\Proposals;

use App\Ai\Agents\ExistingItemPhotoReviewer;
use App\Ai\Agents\ItemPhotoFindingsMerger;
use App\Enums\ProposalField;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Image;
use Throwable;

/**
 * Runs the photo review for one item and records what it would change.
 *
 * Writes nothing to the item. Everything it concludes lands in `item_proposals`
 * for a person to accept or reject, because a vision model is confident whether
 * or not it is right and this data is kept for years.
 *
 * Map-reduce: each photo is described on its own, then the descriptions are
 * reconciled. See ExistingItemPhotoReviewer for why they are not sent together.
 */
class PhotoReviewRunner
{
    /**
     * Review every photo of $item that has not been looked at yet.
     *
     * @return Collection<int, ItemProposal> the proposals recorded, possibly empty
     */
    public function review(Item $item): Collection
    {
        $images = $item->images()->unreviewed()->orderBy('sort_order')->get();

        if ($images->isEmpty()) {
            return collect();
        }

        $findings = $this->describeEach($item, $images);

        // Every photo failed. Leaving analyzed_at null lets the next run retry
        // rather than silently writing the item off as reviewed.
        if ($findings === []) {
            return collect();
        }

        $merged = $this->reconcile($item, $findings);

        // The merge failed, not the photos. Leaving them unanalysed costs a
        // repeat of the vision work; marking them would write the item off as
        // reviewed when nothing was ever concluded about it.
        if ($merged === null) {
            return collect();
        }

        $proposals = $this->record($item, $merged, $images->pluck('id')->all());

        // Only once the findings are safely recorded: a crash between the two
        // would otherwise lose the expensive vision work for good.
        ItemImage::whereIn('id', $images->pluck('id'))->update(['analyzed_at' => now()]);

        return $proposals;
    }

    /**
     * The map half — one vision call per photo.
     *
     * A photo that fails is skipped rather than aborting the item: three good
     * descriptions and one failure is still worth a proposal.
     *
     * @param  Collection<int, ItemImage>  $images
     * @return array<int, array<string, mixed>>
     */
    private function describeEach(Item $item, Collection $images): array
    {
        $findings = [];

        foreach ($images as $image) {
            try {
                $findings[] = (new ExistingItemPhotoReviewer($item->name, $this->language()))
                    ->prompt(
                        'Describe what this photo shows.',
                        attachments: [$this->attachment($image)],
                    )->toArray();
            } catch (Throwable $e) {
                Log::warning('Photo review failed for image {image} of item {item}: {message}', [
                    'image' => $image->id,
                    'item' => $item->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $findings;
    }

    /**
     * The reduce half — text only, and the only step that sees what the owner
     * already wrote.
     *
     * @param  array<int, array<string, mixed>>  $findings
     * @return array<string, mixed>|null null when the merge itself failed
     */
    private function reconcile(Item $item, array $findings): ?array
    {
        try {
            return (new ItemPhotoFindingsMerger($item->name, $item->description, $this->language()))
                ->prompt(
                    "Observations, one per photo:\n".json_encode($findings, JSON_UNESCAPED_UNICODE),
                )->toArray();
        } catch (Throwable $e) {
            Log::warning('Merging photo findings failed for item {item}: {message}', [
                'item' => $item->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $merged
     * @param  array<int, int>  $imageIds
     * @return Collection<int, ItemProposal>
     */
    private function record(Item $item, array $merged, array $imageIds): Collection
    {
        $model = (string) config('ai.vision_model');

        return collect(ProposalField::cases())
            ->map(function (ProposalField $field) use ($item, $merged, $imageIds, $model): ?ItemProposal {
                $proposed = $this->clean($merged[$field->value] ?? null);
                $current = $item->{$field->value};

                if (! $this->isWorthProposing($field, $current, $proposed)) {
                    return null;
                }

                // One open proposal per field. A nightly re-run would otherwise
                // stack near-identical suggestions until the queue is unusable.
                ItemProposal::where('item_id', $item->id)
                    ->where('field', $field)
                    ->pending()
                    ->delete();

                return ItemProposal::create([
                    'item_id' => $item->id,
                    'field' => $field,
                    'current_value' => $current,
                    'proposed_value' => $proposed,
                    'source_image_ids' => $imageIds,
                    'model' => $model,
                ]);
            })
            ->filter()
            ->values();
    }

    /**
     * Whether a suggestion is worth a person's attention.
     *
     * The model cannot be relied on to stay quiet: asked to leave the
     * description alone when it had nothing to add, it returned a reworded
     * version anyway. Left unchecked, every scheduled run would fill the queue
     * with paraphrases and the real finds would be lost among them.
     */
    private function isWorthProposing(ProposalField $field, ?string $current, ?string $proposed): bool
    {
        if ($proposed === null || $proposed === '') {
            return false;
        }

        if ($this->normalise($proposed) === $this->normalise((string) $current)) {
            return false;
        }

        // Identifiers are short and exact: any different value is a real claim
        // worth judging, and there is no prose to compare.
        if ($field !== ProposalField::Description) {
            return true;
        }

        if (blank($current)) {
            return true;
        }

        // Refuse a rewrite that drops what the owner wrote. They know things a
        // photo cannot show — where it came from, what it cost, whose it is —
        // and a proposal that quietly loses that is worse than none.
        if ($this->words($current)->diff($this->words($proposed))->isNotEmpty()) {
            return false;
        }

        return $this->words($proposed)->diff($this->words($current))->isNotEmpty();
    }

    /** @return Collection<int, string> meaningful lowercase words */
    private function words(?string $text): Collection
    {
        return collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $text), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->reject(fn (string $w): bool => mb_strlen($w) < 4)
            ->unique()
            ->values();
    }

    private function normalise(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower((string) $text)) ?? '');
    }

    private function clean(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter($value, 'is_string'));
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function attachment(ItemImage $image): Image
    {
        return Image::fromBase64(
            base64_encode(Storage::disk('public')->get($image->largePath()) ?? ''),
            'image/jpeg',
        );
    }

    private function language(): string
    {
        return config('app.supported_locales.'.app()->getLocale().'.ai', 'English');
    }
}
