<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Push a battery level sample for an item (the main Home Assistant path —
 * an automation reporting a device's battery_level). recorded_at defaults to
 * now; flat-run compression and swap detection happen in BatteryService.
 */
class StoreBatteryReadingRequest extends FormRequest
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
            'percent' => ['required', 'integer', 'min:0', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
