<?php

namespace App\Http\Requests\Item;

use App\Models\Item;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Item $item */
        $item = $this->route('item');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::notIn([$item->id]),
                Rule::exists('items', 'id'),
                function (string $attribute, mixed $value, Closure $fail) use ($item) {
                    if ($value === null) {
                        return;
                    }
                    $candidate = Item::find($value);
                    if ($candidate && $candidate->isDescendantOf($item)) {
                        $fail('Cannot move an item into one of its descendants.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'An item cannot be its own parent.',
        ];
    }
}
