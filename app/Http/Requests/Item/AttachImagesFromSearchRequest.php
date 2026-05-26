<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use Illuminate\Foundation\Http\FormRequest;

class AttachImagesFromSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'urls' => ['required', 'array', 'min:1', 'max:12'],
            // Deep SSRF checks live in RemoteImageDownloader; this is the shallow gate.
            'urls.*' => ['required', 'string', 'url', 'starts_with:https://', 'max:2048'],
        ];
    }
}
