<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user can bulk-edit; same shape as the
        // single-item routes which sit inside `auth` middleware.
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['delete', 'move', 'attach-tag', 'detach-tag'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('items', 'id')],

            // For action=move: target room/container (null = top level).
            // Nullable so the picker can submit "(top level)" explicitly.
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')],

            // For action=attach-tag / detach-tag: tag id.
            'tag_id' => [
                Rule::requiredIf(fn () => in_array($this->input('action'), ['attach-tag', 'detach-tag'], true)),
                'nullable',
                'integer',
                Rule::exists('tags', 'id'),
            ],
        ];
    }
}
