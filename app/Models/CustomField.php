<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomFieldType;
use Database\Factories\CustomFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'type',
        'is_searchable',
        'sort_order',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'is_searchable' => 'bool',
            'sort_order' => 'int',
            'is_system' => 'bool',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (CustomField $field): void {
            if (empty($field->key)) {
                $field->key = self::uniqueKey($field->name, $field->id);
            }
        });
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    private static function uniqueKey(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name, '_') ?: 'field';
        $key = $base;
        $suffix = 2;
        while (self::query()->where('key', $key)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
