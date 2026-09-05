<?php

declare(strict_types=1);

namespace App\Http\Requests\CustomField;

use App\Enums\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomFieldRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CustomFieldType::class)],
            'searchable' => ['boolean'],
        ];
    }
}
