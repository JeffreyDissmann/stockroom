<?php

declare(strict_types=1);

namespace App\Http\Requests\Item\Concerns;

use App\Models\CustomField;

trait HasCustomFieldRules
{
    /**
     * Per-definition validation for the submitted custom field values, keyed by
     * the definition id (`custom_fields.{id}`). System fields are excluded — they
     * are managed by imports, never edited through the item form.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function customFieldRules(): array
    {
        $rules = ['custom_fields' => ['array']];

        foreach (CustomField::query()->where('is_system', false)->get() as $field) {
            $rules["custom_fields.{$field->id}"] = $field->type->rules();
        }

        return $rules;
    }
}
