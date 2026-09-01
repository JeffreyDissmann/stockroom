<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Enums\ItemType;
use App\Http\Requests\Item\Concerns\HasCustomFieldRules;
use App\Http\Requests\Item\Concerns\HasItemDetailRules;
use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'icon' => ['nullable', 'string', 'max:40'],
            'type' => ['required', Rule::enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'images' => ['array', 'max:24'],
            'images.*' => ImageUploadRules::perFile(),
            ...$this->detailRules(),
            ...$this->customFieldRules(),
        ];
    }
}
