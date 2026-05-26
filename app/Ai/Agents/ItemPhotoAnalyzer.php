<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Extracts catalogue fields for a single household item from a product photo,
 * returning structured output the create form can pre-fill. Provider/model are
 * not pinned here — the caller passes the configured vision model so the SDK's
 * provider abstraction (and config('ai.vision_model')) stays in control.
 */
class ItemPhotoAnalyzer implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        You catalogue household belongings for a home-inventory app from a single product photo.

        Identify the main object in the photo and extract concise, factual fields for it:
        - "name": a short human label, ideally "Brand Product" (e.g. "DeWalt 20V Drill"). Always provide one.
        - "manufacturer", "model_number", "serial_number": report a value ONLY if it is clearly
          legible in the image. If it is not visible, return null. Never guess or invent identifiers.
        - "description": one or two neutral, factual sentences describing the item.

        Ignore the background, hands, packaging clutter, price tags, and watermarks.
        PROMPT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Short product name, e.g. "DeWalt 20V Drill".')->required(),
            'manufacturer' => $schema->string()->description('Brand or maker; null if not legible.')->nullable(),
            'model_number' => $schema->string()->description('Model or part number; null if not legible.')->nullable(),
            'serial_number' => $schema->string()->description('Serial number; only if clearly legible, else null.')->nullable(),
            'description' => $schema->string()->description('One or two factual sentences about the item.')->nullable(),
        ];
    }
}
