<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Record an explicit battery swap for an item (a button or automation, when
 * the change isn't inferred from a level jump). changed_at may be backdated
 * but never lie in the future; the swap closes the current cycle and opens a
 * fresh one, completing the "Replace battery" reminder.
 */
class StoreBatteryChangeRequest extends FormRequest
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
