<?php

declare(strict_types=1);

namespace App\Http\Requests\Battery;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The item page's "Change battery" action — record a swap done by hand (e.g.
 * you replaced it before Home Assistant reported the fresh level). Both fields
 * are optional: a bare button press records a swap now with no note.
 */
class ChangeBatteryRequest extends FormRequest
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
            'changed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
