<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ItemType;
use App\Http\Requests\Item\Concerns\HasItemDetailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Partial (PATCH) update of an item. `name`/`type` use `sometimes` so a
 * client can send just the fields it wants to change (e.g. quantity); only
 * provided keys are validated and applied. Detail fields are all nullable.
 */
class UpdateItemRequest extends FormRequest
{
    use HasItemDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:40'],
            'type' => ['sometimes', 'required', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            ...$this->detailRules(),
        ];
    }
}
