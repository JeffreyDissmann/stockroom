<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'color'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('tag');
    }

    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if (empty($tag->slug) || $tag->isDirty('name')) {
                $tag->slug = self::uniqueSlug($tag->name, $tag->id);
            }
        });
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class);
    }

    private static function uniqueSlug(string $name, ?int $ignoreId): string
    {
        $base = Str::slug($name) ?: 'tag';
        $slug = $base;
        $suffix = 2;
        while (self::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
