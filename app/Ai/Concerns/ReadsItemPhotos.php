<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

/**
 * The rules every photo-reading agent must state, in one place.
 *
 * NewItemDraftFromPhoto (create an item from a photo) and ExistingItemPhotoReviewer
 * (review an existing item's photos) ask a vision model for the same four
 * things — manufacturer, model number, serial number, a description — and so
 * need the same guardrails. They had drifted already: one told the model to
 * ignore "price tags and watermarks", the other "floors", each having learned
 * a different lesson from a different bad output.
 *
 * Keeping the rules here means a lesson learned by one agent is not lost on the
 * other. The schemas stay separate: what the two agents *return* genuinely
 * differs, and only the guidance is shared.
 */
trait ReadsItemPhotos
{
    /**
     * Why this is worth stating so bluntly: a hallucinated serial number is far
     * more expensive than a missing one. A blank field prompts someone to go and
     * look; a plausible wrong one is trusted for years and only discovered when
     * it matters — a warranty claim, an insurance list, a support call.
     */
    protected function identifierHonestyRule(): string
    {
        return <<<'RULE'
        Report "manufacturer", "model_number" and "serial_number" ONLY when they are
        clearly legible in a photo. If you cannot read one, return null for it.
        NEVER invent, guess, or infer an identifier from what the object resembles —
        a missing value is always better than a plausible wrong one.
        RULE;
    }

    /** Everything that is in shot but is not the item. */
    protected function ignoreClutterRule(): string
    {
        return 'Ignore anything that is not the item itself: backgrounds, floors, hands, '
            .'the surface it rests on, price tags, watermarks, and packaging it merely sits in.';
    }

    /**
     * Prose is translated; identifiers are transcribed. "DCD778" is not German
     * or English, and a model that "translates" a model number has invented one.
     */
    protected function languageRule(string $language): string
    {
        return "Write prose in {$language}. Keep brand names, model numbers and serial "
            .'numbers exactly as printed on the object — identifiers are never translated.';
    }
}
