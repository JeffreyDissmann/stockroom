<?php

namespace App\Http\Requests\Tag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var \App\Models\Tag $tag */
        $tag = $this->route('tag');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('tags', 'name')->ignore($tag->id)],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
        ];
    }
}
