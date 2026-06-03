<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Set (or replace) the Home Assistant entity linked to an item. Home
 * Assistant owns this data: it supplies the entity id and the full URL to its
 * own device page, so there's no URL composition on our side.
 */
class StoreHomeAssistantLinkRequest extends FormRequest
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
            'ha_entity_id' => ['required', 'string', 'max:255'],
            'ha_device_id' => ['nullable', 'string', 'max:255'],
            'friendly_name' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'instance_id' => ['nullable', 'string', 'max:255'],
        ];
    }
}
