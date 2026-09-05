<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use App\Support\ImageUploadRules;
use Illuminate\Foundation\Http\FormRequest;

class AnalyzeItemPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        // Mirrors StoreItemImagesRequest's per-file rules: one photo to analyse.
        return [
            'photo' => ['required', ...ImageUploadRules::perFile()],
        ];
    }
}
