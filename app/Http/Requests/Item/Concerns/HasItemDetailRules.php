<?php

declare(strict_types=1);

namespace App\Http\Requests\Item\Concerns;

trait HasItemDetailRules
{
    /**
     * Validation rules shared by item create + update for the optional
     * detail fields (quantity, purchase, identification, warranty, sale).
     *
     * @return array<string, array<int, mixed>>
     */
    protected function detailRules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'purchased_from' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            'manufacturer' => ['nullable', 'string', 'max:255'],
            'model_number' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],

            // Free string: App\Enums\BatteryType is the curated picker, but an
            // unusual cell can still be recorded (and HA can sync any value).
            'battery_type' => ['nullable', 'string', 'max:255'],

            'lifetime_warranty' => ['boolean'],
            'warranty_expires' => ['nullable', 'date'],
            'warranty_details' => ['nullable', 'string', 'max:2000'],

            'sold_to' => ['nullable', 'string', 'max:255'],
            'sold_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'sold_date' => ['nullable', 'date'],
            'sold_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
