<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-household key/value store for admin-editable preferences. The
 * `value` column is JSON-encoded so one row can hold ints, strings,
 * booleans, or arrays without a separate type column.
 *
 * Use the static helpers — `Setting::get('box_tag_id')` /
 * `Setting::set('box_tag_id', 7)` / `Setting::int('box_tag_id')` —
 * rather than the Eloquent API directly.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $casts = [
        // Laravel's array cast json_encode's on write and json_decode's on
        // read — scalars round-trip naturally (an int 5 stores as '5' and
        // decodes back to 5), so no wrap-in-array trick is needed.
        'value' => 'array',
    ];

    /**
     * Look up a setting by key, returning the decoded value or $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return self::query()->where('key', $key)->value('value') ?? $default;
    }

    /**
     * Upsert a setting by key.
     */
    public static function set(string $key, mixed $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Convenience accessor for settings whose value is expected to be a
     * positive integer (typically a foreign-key id). Returns null when the
     * setting is absent or cleared by an admin.
     */
    public static function int(string $key): ?int
    {
        $value = self::get($key);

        return is_int($value) ? $value : null;
    }
}
