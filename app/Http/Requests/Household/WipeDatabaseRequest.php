<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class WipeDatabaseRequest extends FormRequest
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
            'include_tags' => ['boolean'],
            'include_custom_fields' => ['boolean'],
            'include_activity' => ['boolean'],
        ];
    }
}
