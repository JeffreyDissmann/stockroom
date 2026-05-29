<?php

declare(strict_types=1);

namespace App\Http\Requests\Item;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Optional overrides for the "create a box for this item" action. Every field
 * is nullable — when omitted, the controller falls back to a value derived
 * from the source item (name prefixed with "BOX: ", serial/manufacturer/
 * description/quantity copied verbatim).
 */
class StoreBoxRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Box creation is open to every authenticated user — consistent with
        // item edit being unrestricted. The auth middleware on the route is
        // the gate; nothing extra needed here.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
