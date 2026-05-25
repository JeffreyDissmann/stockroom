<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Url = 'url';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Boolean => 'Yes / No',
            self::Url => 'Link',
        };
    }

    /**
     * Validation rules for an incoming value of this type. Always nullable —
     * an unset custom field is allowed.
     *
     * @return array<int, string>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Text => ['nullable', 'string', 'max:2000'],
            self::Number => ['nullable', 'numeric'],
            self::Date => ['nullable', 'date'],
            self::Boolean => ['nullable', 'boolean'],
            self::Url => ['nullable', 'url', 'max:2000'],
        };
    }

    /**
     * Serialise an incoming (typed) value to the string stored in the database.
     */
    public function serialize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this) {
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Cast a stored string back to its typed representation for the frontend.
     */
    public function cast(?string $value): string|int|float|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($this) {
            self::Boolean => $value === '1',
            self::Number => str_contains($value, '.') ? (float) $value : (int) $value,
            default => $value,
        };
    }
}
