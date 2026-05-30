<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * One-shot extractor for inventory-worthy items from a Paperless-ngx
 * document's OCR text. Used by ProcessPaperlessDocumentJob (#7).
 *
 * Returns a structured `{ items: [...] }` object so a single receipt can
 * produce N Stockroom items in one LLM call. Provider/model are chosen
 * by the caller via the `model:` argument to prompt(); ai.chat_model is
 * the natural pick (text-only, tool-calling capable models cope with
 * structured output too).
 */
class DocumentExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  string  $language  Language for the human-readable fields
     *                            (name, description), e.g. "English" or
     *                            "German". Identifier fields are never
     *                            translated.
     */
    public function __construct(private readonly string $language = 'English') {}

    public function instructions(): string
    {
        return <<<PROMPT
        You analyse OCR text from a single Paperless-ngx document — typically
        a receipt, invoice, warranty card, or product manual — and extract a
        list of inventory-worthy physical goods to add to a home inventory.

        For each item, return:
        - "name": short label like "Brand Product" (e.g. "Apple iPhone 15 Pro",
          "DeWalt 20V Cordless Drill"). Always provide one.
        - "manufacturer", "model_number", "serial_number": ONLY if they appear
          verbatim in the text. Never invent or guess identifiers; return null
          when absent.
        - "purchase_price": per-line item price as a decimal number, only
          when a per-line price is clearly visible. Do NOT use shipping,
          totals, taxes, or grand totals.
        - "purchase_date": the document's purchase / order date in ISO format
          (YYYY-MM-DD) if a clear date is present; null otherwise. The same
          date applies to all items on a receipt.
        - "quantity": positive integer; default to 1 when not stated.
        - "description": one neutral, factual sentence about the item. Optional.

        Skip line items that are clearly shipping, fees, discounts, taxes,
        services, or non-physical (e.g. extended warranties, gift cards).
        Only return goods worth tracking in a home inventory.

        Write "name" and "description" in {$this->language}. Keep brand and
        product names as printed on the document. Identifier fields
        (manufacturer, model_number, serial_number) are never translated.

        If the document doesn't describe any inventory-worthy items, return
        an empty `items` array.
        PROMPT;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'items' => $schema->array()
                ->items(
                    $schema->object([
                        'name' => $schema->string()->description('Short "Brand Product" label.')->required(),
                        'manufacturer' => $schema->string()->description('Brand or maker; null if absent.')->nullable(),
                        'model_number' => $schema->string()->description('Model or part number; null if absent.')->nullable(),
                        'serial_number' => $schema->string()->description('Serial number; null if absent.')->nullable(),
                        'purchase_price' => $schema->number()->description('Per-line price as decimal; null if not visible.')->nullable(),
                        'purchase_date' => $schema->string()->description('ISO YYYY-MM-DD purchase date; null if absent.')->nullable(),
                        'quantity' => $schema->integer()->description('Positive integer; default 1.'),
                        'description' => $schema->string()->description('Optional one-sentence factual description.')->nullable(),
                    ]),
                )
                ->description('Inventory-worthy items extracted from the document.'),
        ];
    }
}
