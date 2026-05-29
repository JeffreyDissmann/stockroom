<?php

declare(strict_types=1);

namespace App\Http\Requests\Household;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}
