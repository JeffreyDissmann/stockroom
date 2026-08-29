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
 * Reviews ONE photo of an existing item and reports what it shows.
 *
 * Two shapes of photo matter, and the model decides which it is looking at:
 *
 *  - a CONTAINER of many things (a moving box of children's clothes, shoes and
 *    a toy) — worth listing the contents, because photos are not searchable and
 *    the only way that box becomes findable is words in its description;
 *  - a SINGLE object — worth reading the identifiers off it, which is what
 *    someone actually wants when they look the item up later.
 *
 * One photo per call, deliberately. Passing several images alongside a
 * structured-output schema was tested and the model read only the LAST one,
 * silently ignoring the rest — reproducibly, and following the order. An item
 * with three photos would have been catalogued from the third alone with
 * nothing to show anything was wrong.
 *
 * So each photo is analysed on its own and ItemPhotoFindingsMerger reconciles
 * the results afterwards. That also removes the anchoring a chained approach
 * suffers, where the model leans on what it was told about the previous photo
 * instead of looking at this one.
 */
class ExistingItemPhotoReviewer implements Agent, HasStructuredOutput
{
    use Promptable;
    use ReadsItemPhotos;

    /**
     * @param  string  $itemName  Anchors the analysis on the right subject.
     * @param  string  $language  Language for prose. Identifiers are never
     *                            translated.
     */
    public function __construct(
        private readonly string $itemName,
        private readonly string $language = 'English',
    ) {}

    /**
     * Reading a photograph, so the vision model. Declared here rather than left
     * to each caller: the agent knows what kind of model it needs, and a caller
     * passing the chat model by mistake fails at inference time with an error
     * that says nothing about the cause.
     */
    public function model(): string
    {
        return (string) config('ai.vision_model');
    }

    /**
     * Vision inference on self-hosted hardware runs to tens of seconds, and longer
     * when the machine is doing anything else. The SDK's 60s default silently
     * turned a whole nightly run into "nothing to add": every call timed out, each
     * was caught and logged, and the report looked like the model simply had no
     * suggestions. Nobody waits on this job, so it can afford to be patient.
     */
    public function timeout(): int
    {
        return (int) config('ai.agent_timeout', 180);
    }

    public function instructions(): string
    {
        $identifiers = $this->identifierHonestyRule();
        $clutter = $this->ignoreClutterRule();
        $language = $this->languageRule($this->language);

        return <<<PROMPT
        You are shown ONE photo of a home-inventory item, "{$this->itemName}".
        Report only what this photo shows. Another step combines your answer with
        the item's other photos and its existing description, so do not speculate
        about anything outside this frame.

        First decide what you are looking at and set "kind":
        - "collection" — a box, crate, drawer or bag holding many separate things.
        - "single" — one object, possibly photographed more than once.

        For a "collection": fill "contents" with the distinct kinds of thing you can
        actually SEE inside (e.g. "children's shoes", "wool jumper", "board game").
        Group sensibly rather than counting every sock. These words are the only way
        this box can be found by search later, so be concrete about what is there.
        Leave the identifier fields null.

        For a "single": read "manufacturer", "model_number" and "serial_number" off
        the object, and report each ONLY when it is clearly legible in a photo.
        Leave "contents" empty.

        Then write "description": one or two factual sentences about what THIS photo
        shows. Plain prose — no markdown or ** bold **. Describe the object, not the
        photograph: "a black soundbar with a fabric grille", never "the image shows"
        or "no serial number is visible".

        {$identifiers}
        Never list contents you cannot actually see either.

        {$clutter}

        {$language}
        PROMPT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()
                ->enum(['single', 'collection'])
                ->description('"collection" when the photos show a container of many things, else "single".')
                ->required(),
            'contents' => $schema->array()
                ->items($schema->string())
                ->description('Distinct visible contents; empty for a single object.'),
            'description' => $schema->string()
                ->description('Existing description combined with what the photos add; null when they add nothing.')
                ->nullable(),
            'manufacturer' => $schema->string()
                ->description('Brand or maker, only if legible on the object; else null.')
                ->nullable(),
            'model_number' => $schema->string()
                ->description('Model or part number, only if legible; else null.')
                ->nullable(),
            'serial_number' => $schema->string()
                ->description('Serial number, only if clearly legible; else null.')
                ->nullable(),
        ];
    }
}
