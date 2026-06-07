<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * One-shot extractor of a SINGLE item's catalogue fields from a Paperless
 * document's OCR text — the "suggest updates from this document" flow on
 * the item edit page. Sibling of DocumentExtractor, which extracts a LIST
 * of new items at intake; this one knows which item the document is linked
 * to and proposes values for that item's fields only. The UI decides what
 * to apply — proposals never overwrite anything by themselves.
 */
class ItemFieldExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  string  $itemName  The linked item, so a multi-product receipt
     *                            yields fields for the right line.
     * @param  string  $language  Language for the human-readable fields
     *                            (name, description). Identifier fields are
     *                            never translated.
     */
    public function __construct(
        private readonly string $itemName,
        private readonly string $language = 'English',
    ) {}

    public function instructions(): string
    {
        return <<<PROMPT
        You analyse OCR text from a single Paperless-ngx document — typically a
        receipt, invoice, warranty card, or product manual — that is linked to
        ONE existing home-inventory item: "{$this->itemName}".

        Extract that item's catalogue fields from the text. The document may
        mention several products; only use the lines belonging to this item.
        Return null for every field the text does not clearly support:
        - "name": a short "Brand Product" label for the item, only when the
          document names it more precisely than "{$this->itemName}".
        - "manufacturer", "model_number", "serial_number": ONLY if they appear
          verbatim in the text. Never invent or guess identifiers.
        - "purchase_price": the item's per-line price as a decimal number. Do
          NOT use shipping, totals, taxes, or grand totals.
        - "purchase_date": the purchase / order date in ISO format
          (YYYY-MM-DD) if a clear date is present.
        - "purchased_from": the vendor / shop / merchant that sold the item.
        - "quantity": positive integer, only when the document clearly states
          a quantity for this item.
        - "description": one neutral, factual sentence about the item.

        Write "name" and "description" in {$this->language}. Keep brand and
        product names as printed on the document. Identifier fields are never
        translated.
        PROMPT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Short "Brand Product" label; null when the document adds nothing.')->nullable(),
            'manufacturer' => $schema->string()->description('Brand or maker; null if absent.')->nullable(),
            'model_number' => $schema->string()->description('Model identifier as printed; null if absent.')->nullable(),
            'serial_number' => $schema->string()->description('Serial number as printed; null if absent.')->nullable(),
            'purchase_price' => $schema->number()->description('Per-line price; null if not clearly visible.')->nullable(),
            'purchase_date' => $schema->string()->description('Purchase/order date, YYYY-MM-DD; null if absent.')->nullable(),
            'purchased_from' => $schema->string()->description('Vendor / shop; null if absent.')->nullable(),
            'quantity' => $schema->integer()->description('Stated quantity; null when not stated.')->nullable(),
            'description' => $schema->string()->description('One factual sentence; null when the text supports none.')->nullable(),
        ];
    }
}
