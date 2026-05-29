<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-household key/value store for admin-editable preferences. Values are
 * persisted as JSON text so one row can hold ints, strings, booleans, or arrays
 * without a separate type column.
 *
 * Use the static `get()` / `set()` helpers — `Setting::get('box_tag_id')` /
 * `Setting::set('box_tag_id', 7)` — rather than the Eloquent API directly.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        'value' => 'array',
    ];

    /**
     * Look up a setting by key, returning the decoded value or $default.
     *
     * Because `value` is cast to array, scalars are stored wrapped (`[7]`) and
     * unwrapped on read so callers see the natural type. A missing row returns
     * $default rather than throwing.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = self::query()->where('key', $key)->first();

        if ($row === null) {
            return $default;
        }

        $value = $row->value;

        // Single-scalar values are stored as a 1-element array (see set()).
        // Unwrap them so callers see the natural type.
        if (is_array($value) && array_keys($value) === [0]) {
            return $value[0];
        }

        return $value;
    }

    /**
     * Upsert a setting by key. Scalars are wrapped in a single-element array
     * so the JSON cast round-trips them without ambiguity.
     */
    public static function set(string $key, mixed $value): void
    {
        $stored = is_array($value) ? $value : [$value];

        self::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
    }
}
