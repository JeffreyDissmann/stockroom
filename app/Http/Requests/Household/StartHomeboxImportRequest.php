<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use Illuminate\Foundation\Http\FormRequest;

class StartHomeboxImportRequest extends FormRequest
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
            'url' => ['required', 'url'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }
}
