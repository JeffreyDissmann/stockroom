<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Link this item to another item." Carries just a single field — the
 * partner item's id — which must exist. Self-link is enforced at the model
 * level via Item::linkRelated; we don't duplicate that rule here so a
 * single source of truth holds.
 */
class StoreRelatedItemRequest extends FormRequest
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
            'related_item_id' => ['required', 'integer', 'exists:items,id'],
        ];
    }
}
