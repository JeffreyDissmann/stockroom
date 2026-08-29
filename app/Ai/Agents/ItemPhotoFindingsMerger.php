<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Concerns\ReadsItemPhotos;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Reconciles the per-photo findings of ExistingItemPhotoReviewer into one
 * suggestion for the item.
 *
 * The reduce half of the pair. It never sees a photograph — the vision work is
 * already done, and this is a text problem: several overlapping observations of
 * the same thing, plus whatever the owner already wrote, to be folded into a
 * single description without losing either.
 *
 * That distinction matters practically. Vision inference costs roughly fifteen
 * seconds a photo; this step runs on the chat model in about one, and can be
 * changed without re-running the expensive half.
 *
 * It is also the only place that sees the existing description, which is
 * deliberate: the reviewer must describe what it can see, uninfluenced by what
 * someone has already claimed is in the box.
 */
class ItemPhotoFindingsMerger implements Agent, HasStructuredOutput
{
    use Promptable;
    use ReadsItemPhotos;

    /**
     * @param  string  $itemName  The subject all findings describe.
     * @param  string|null  $currentDescription  What the owner already wrote.
     *                                           Preserved, never overwritten.
     * @param  string  $language  Language for prose.
     */
    public function __construct(
        private readonly string $itemName,
        private readonly ?string $currentDescription = null,
        private readonly string $language = 'English',
    ) {}

    /**
     * Text only — the vision work is already done — so this runs on the chat
     * model. Roughly a second against fifteen for a vision call.
     */
    public function model(): string
    {
        return (string) config('ai.chat_model');
    }

    /**
     * Text-only and normally a second or two, but it queues behind the vision calls
     * on the same endpoint. Generous for the same reason as the reviewer: a timeout
     * here throws away every vision call that produced the findings.
     */
    public function timeout(): int
    {
        return (int) config('ai.agent_timeout', 120);
    }

    public function instructions(): string
    {
        $clutter = $this->ignoreClutterRule();

        $existing = filled($this->currentDescription)
            ? "The owner has already written this description:\n\"{$this->currentDescription}\""
            : 'The item has no description yet.';

        return <<<PROMPT
        You are given what a vision model observed in each photo of ONE
        home-inventory item, "{$this->itemName}". Combine those observations into a
        single catalogue entry.

        {$existing}

        The photos are all of this same item. Where two photos describe the same
        thing, say it once — the same jumper seen from two angles is one jumper, not
        two. Where they describe different things, keep both.

        Write "description" as ONE text that keeps everything the owner already
        wrote, then adds what the photos reveal and the description does not already
        cover. Their words come first and are never dropped, reworded or contradicted:
        they know things a photo cannot show — where it came from, what it cost, who
        it belongs to.

        ALWAYS write the best combined description you can. Do not decide for
        yourself whether it is worth proposing and return null instead — something
        after you compares your text with what is already recorded and drops it if it
        adds nothing. Staying quiet here only loses good work.

        Write plain prose. No markdown, no ** bold **, no bullet points — this text
        goes straight into a description field and asterisks would be shown literally.

        Describe the item, never the photograph or your own certainty. "Contains
        orange and blue track pieces" is useful; "the photo shows no further details"
        and "no model number is visible" describe your search rather than the object,
        and are worth nothing to someone reading the entry later. If you have nothing
        to add beyond what is already written, simply repeat what is already written.

        {$clutter}

        Merge "contents" into one deduplicated list across all photos.

        For "manufacturer", "model_number" and "serial_number": if ANY observation
        reports a value, carry it through verbatim — one photo reading a brand off the
        object is enough, and the others saying nothing is not disagreement. Only
        return null when no observation reported one. Never combine fragments from
        different photos into an identifier no single photo showed, and never invent
        one. If two observations genuinely conflict, prefer the more specific.

        Write prose in {$this->language}. Keep identifiers exactly as reported.
        PROMPT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'contents' => $schema->array()
                ->items($schema->string())
                ->description('Deduplicated contents across every photo; empty for a single object.'),
            'description' => $schema->string()
                ->description('Existing description plus what the photos add; null when they add nothing.')
                ->nullable(),
            'manufacturer' => $schema->string()->description('Only if a photo reported it; else null.')->nullable(),
            'model_number' => $schema->string()->description('Only if a photo reported it; else null.')->nullable(),
            'serial_number' => $schema->string()->description('Only if a photo reported it; else null.')->nullable(),
        ];
    }
}
