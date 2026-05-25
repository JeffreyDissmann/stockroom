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

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

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
}
