<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Enums\ItemType;
use App\Http\Requests\Item\Concerns\HasCustomFieldRules;
use App\Http\Requests\Item\Concerns\HasItemDetailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreItemRequest extends FormRequest
{
    use HasCustomFieldRules;
    use HasItemDetailRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'images' => ['array', 'max:24'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,heic',
                'max:10240',
                'dimensions:min_width=64,min_height=64',
            ],
            ...$this->detailRules(),
            ...$this->customFieldRules(),
        ];
    }
}
