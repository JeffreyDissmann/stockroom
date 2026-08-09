<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use App\Enums\ItemType;
use App\Services\Battery\BatteryThreshold;
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

            // Which tag is auto-assigned to battery-tracked items. Like the
            // Home Assistant tag: no opt-out, unset until the first reading
            // creates it, so the picker only switches between existing tags.
            'battery_tag_id' => ['nullable', 'integer', 'exists:tags,id'],

            // The percent at or below which a battery counts as low. `sometimes`
            // rather than `required`: this endpoint accepts partial updates (a
            // caller may be changing only a tag), and there is no "off" state to
            // express with null. Bounds come from BatteryThreshold so validation
            // can't drift from the range the accessor will actually honour.
            'battery_low_threshold' => [
                'sometimes',
                'integer',
                'min:'.BatteryThreshold::MIN,
                'max:'.BatteryThreshold::MAX,
            ],

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
