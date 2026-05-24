<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'description',
    ];

    protected $casts = [
        'type' => ItemType::class,
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return Collection<int, self> Ordered root -> direct parent.
     */
    public function ancestors(): Collection
    {
        $chain = collect();
        $current = $this->parent;
        while ($current !== null) {
            $chain->prepend($current);
            $current = $current->parent;
        }

        return $chain;
    }

    public function isDescendantOf(self $other): bool
    {
        return $this->ancestors()->contains(fn (self $ancestor) => $ancestor->is($other));
    }
}
