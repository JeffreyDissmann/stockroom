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

            // For action=attach-tag / detach-tag: tag id. Note the lack of
            // `nullable` — pairing it with `required_if` lets a null value
            // through (the framework treats nullable as "skip the rest of
            // the rules when null"), so the tag-op paths would silently
            // accept a missing tag_id.
            'tag_id' => [
                'required_if:action,attach-tag,detach-tag',
                'integer',
                Rule::exists('tags', 'id'),
            ],
        ];
    }
}
