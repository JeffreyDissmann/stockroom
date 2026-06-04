<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\ItemType;
use App\Http\Requests\Item\Concerns\HasItemDetailRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Create an item via the API (e.g. Home Assistant auto-creating a Stockroom
 * item for a device). Reuses the shared detail-field rules. Image uploads and
 * custom fields are out of scope for the JSON API — those stay in the web UI.
 */
class StoreItemRequest extends FormRequest
{
    use HasItemDetailRules;

    public function authorize(): bool
    {
        // Token presence + the `write` ability (enforced by route middleware)
        // is the authorization; the single household has no per-user scoping.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:40'],
            'type' => ['required', new Enum(ItemType::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('items', 'id')],
            'tags' => ['array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            ...$this->detailRules(),
        ];
    }
}
