<?php

namespace App\Http\Requests\Item;

use App\Enums\ItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateItemRequest extends FormRequest
{
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
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
        ];
    }
}
