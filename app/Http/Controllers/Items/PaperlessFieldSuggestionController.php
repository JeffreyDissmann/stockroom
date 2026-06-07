<?php

declare(strict_types=1);

namespace App\Http\Controllers\Items;

use App\Ai\Agents\ItemFieldExtractor;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAiEnabled;
use App\Http\Middleware\EnsurePaperlessEnabled;
use App\Models\Item;
use App\Services\Paperless\PaperlessClient;
use ArrayAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Throwable;

/**
 * "Suggest updates from this document": re-reads a linked Paperless doc's
 * OCR text and proposes values for the item's catalogue fields, as JSON for
 * the edit form. Read-only — the user reviews every proposal in the form
 * (conflicts surface as explicit per-field choices) and nothing persists
 * until they save.
 *
 * Member-accessible by the linked-document precedent: the doc is already
 * attached to an item, so its content is household-visible — unlike search,
 * which can discover unlinked documents and stays admin-only.
 */
#[Middleware(EnsurePaperlessEnabled::class)]
#[Middleware(EnsureAiEnabled::class)]
class PaperlessFieldSuggestionController extends Controller
{
    public function __invoke(Item $item, int $document, PaperlessClient $client): JsonResponse
    {
        // 404, not 403: an unlinked document id should be indistinguishable
        // from a nonexistent one.
        abort_unless(
            $item->paperlessLinks()->where('paperless_document_id', $document)->exists(),
            404,
        );

        try {
            $ocr = (string) ($client->document($document)['content'] ?? '');
        } catch (Throwable) {
            abort(502, 'The document could not be fetched from Paperless.');
        }

        if (trim($ocr) === '') {
            abort(422, 'The document has no readable text to extract from.');
        }

        // Propose name/description in the user's language (set by SetLocale);
        // identifiers are kept verbatim by the agent regardless.
        $language = config('app.supported_locales.'.app()->getLocale().'.ai', 'English');

        try {
            $response = (new ItemFieldExtractor($item->name, $language))->prompt(
                "Extract this item's catalogue fields from the document.\n\nOCR TEXT:\n{$ocr}",
                model: config('ai.chat_model'),
                timeout: 120,
            );
        } catch (Throwable) {
            abort(502, 'The document could not be analysed. Please try again or fill the form manually.');
        }

        abort_unless($response instanceof ArrayAccess, 502, 'Unexpected response from the model.');

        return response()->json(['fields' => $this->normalisedFields($response)]);
    }

    /**
     * A stable, known-shape payload: only our schema keys, trimmed/typed,
     * with blanks and junk normalised to null so the form only ever sees
     * confident values.
     *
     * @return array<string, string|float|int|null>
     */
    private function normalisedFields(ArrayAccess $response): array
    {
        $fields = [];

        foreach (['name', 'manufacturer', 'model_number', 'serial_number', 'purchased_from', 'description'] as $key) {
            $value = $response[$key] ?? null;
            $fields[$key] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        $price = $response['purchase_price'] ?? null;
        $fields['purchase_price'] = is_numeric($price) && (float) $price >= 0 ? (float) $price : null;

        $quantity = $response['quantity'] ?? null;
        $fields['quantity'] = is_numeric($quantity) && (int) $quantity >= 1 ? (int) $quantity : null;

        $fields['purchase_date'] = $this->validDate($response['purchase_date'] ?? null);

        return $fields;
    }

    /**
     * Strictly-formatted extraction dates only — a model paraphrasing
     * "sometime in 2023" must not reach the form's date input.
     */
    private function validDate(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
