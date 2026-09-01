<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreItemImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:24'],
            'images.*' => ImageUploadRules::perFile(),
        ];
    }
}
