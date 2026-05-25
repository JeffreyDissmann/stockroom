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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use Searchable;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'description',
        'quantity',
        'purchased_from',
        'purchase_date',
        'purchase_price',
        'manufacturer',
        'model_number',
        'serial_number',
        'lifetime_warranty',
        'warranty_expires',
        'warranty_details',
        'sold_to',
        'sold_price',
        'sold_date',
        'sold_notes',
    ];

    protected $casts = [
        'type' => ItemType::class,
        'quantity' => 'int',
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'lifetime_warranty' => 'bool',
        'warranty_expires' => 'date',
        'sold_price' => 'decimal:2',
        'sold_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Item $item): void {
            $item->images->each(fn (ItemImage $image) => $image->delete());
        });
    }

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

    public function images(): HasMany
    {
        return $this->hasMany(ItemImage::class)->orderBy('sort_order');
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ItemImage::class)->where('is_primary', true);
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

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['tags', 'customFieldValues']);

        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'serial_number' => $this->serial_number,
            'tags' => $this->tags->pluck('name')->implode(' '),
            'custom_fields' => $this->customFieldValues->pluck('value')->filter()->implode(' '),
            'location_path' => $this->locationPath(),
        ];
    }

    /**
     * IDs of every item beneath this one, at any depth. Uses explicit queries so
     * it's safe under Model::preventLazyLoading().
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];

        while ($frontier !== []) {
            $children = self::query()->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    /**
     * Re-index this item's descendants for search. Their location_path embeds this
     * item's name and position, so it goes stale when this item is renamed or moved.
     */
    public function reindexDescendants(): void
    {
        $ids = $this->descendantIds();

        if ($ids !== []) {
            self::query()->whereKey($ids)->get()->searchable();
        }
    }

    /**
     * The human-readable ancestor path ("Room / Container"), built with explicit
     * queries so it works under Model::preventLazyLoading() (incl. chunked indexing).
     */
    public function locationPath(): string
    {
        $names = [];
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $parent = self::query()->find($parentId, ['id', 'parent_id', 'name']);
            if ($parent === null) {
                break;
            }
            array_unshift($names, $parent->name);
            $parentId = $parent->parent_id;
        }

        return implode(' / ', $names);
    }
}
