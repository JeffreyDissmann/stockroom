<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Enums\ItemType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is admin-gated by middleware; nothing extra needed here.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Nullable so an admin can opt out of auto-tagging boxes. When
            // present it must point at an existing tag.
            'box_tag_id' => ['nullable', 'integer', 'exists:tags,id'],

            // Which tag is auto-assigned to Home Assistant-linked items. There
            // is no opt-out (linking always tags) — nullable only because the
            // setting is unset until the first link creates the tag; the
            // preferences picker only appears once it exists and offers no
            // "none" choice, so it can only switch to another existing tag.
            'home_assistant_tag_id' => ['nullable', 'integer', 'exists:tags,id'],

            // Paperless intake parent: nullable (opt out → items land at top
            // level), and when set must be a room or container — anything
            // else would mean dropping items inside another item, which the
            // Stockroom model doesn't model.
            'paperless_parent_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->whereIn('type', [
                    ItemType::Room->value,
                    ItemType::Container->value,
                ]),
            ],
        ];
    }
}
