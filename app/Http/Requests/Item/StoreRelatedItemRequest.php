<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Link this item to another item." Carries just a single field — the
 * partner item's id — which must exist and must not be the item itself.
 * Self-link prevention lives here (not in the controller try/catch) so all
 * input validation is in one place; the model still guards as a backstop.
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
        $sourceItemId = $this->route('item')->id;

        return [
            'related_item_id' => [
                'required',
                'integer',
                'exists:items,id',
                // Reject self-link before the model's InvalidArgumentException
                // ever fires. The Rule::notIn() reads naturally and the error
                // message surfaces on the picker's InputError binding.
                Rule::notIn([$sourceItemId]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'related_item_id.not_in' => __('items.related.cannot_link_to_self'),
        ];
    }
}
