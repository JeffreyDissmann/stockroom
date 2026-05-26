<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Enums\ItemType;
use App\Http\Requests\Item\Concerns\HasCustomFieldRules;
use App\Http\Requests\Item\Concerns\HasItemDetailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateItemRequest extends FormRequest
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
            'type' => ['required', new Enum(ItemType::class)],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            ...$this->detailRules(),
            ...$this->customFieldRules(),
        ];
    }
}
