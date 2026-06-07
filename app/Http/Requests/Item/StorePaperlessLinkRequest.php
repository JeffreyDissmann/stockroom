<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Services\Paperless\PaperlessLinker;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Link this item to a Paperless document." Carries a single field — the
 * document reference, as a bare id ("447") or a pasted Paperless URL
 * ("https://paperless.host/documents/447/"). Parseability is validated
 * here; whether the document actually exists is the controller's call to
 * Paperless (a remote check doesn't belong in a validation rule).
 */
class StorePaperlessLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Linking is open to every authenticated user, like item edit.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (PaperlessLinker::parseDocumentReference((string) $value) === null) {
                        $fail(__('validation.custom.paperless_document.unparseable'));
                    }
                },
            ],
        ];
    }

    /**
     * The parsed document id. Only valid after validation passed.
     */
    public function documentId(): int
    {
        return (int) PaperlessLinker::parseDocumentReference((string) $this->validated()['document']);
    }
}
