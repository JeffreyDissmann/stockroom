<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemType;
use App\Search\ItemEmbedder;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    use LogsActivity;
    use Searchable;

    protected $fillable = [
        'parent_id',
        'type',
        'name',
        'icon',
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

    public function getActivitylogOptions(): LogOptions
    {
        // Log the parent's name (not the opaque id) so a move reads "Garage -> Shed".
        return LogOptions::defaults()
            ->logFillable()
            ->logOnly(['parent.name'])
            ->logExcept(['parent_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('item');
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
        $this->loadMissing(['tags', 'customFieldValues.field']);

        $document = [
            'id' => (string) $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type->value,
            'manufacturer' => $this->manufacturer,
            'model_number' => $this->model_number,
            'serial_number' => $this->serial_number,
            'tags' => $this->tags->pluck('name')->implode(' '),
            'custom_fields' => $this->customFieldValues
                ->filter(fn (CustomFieldValue $value): bool => (bool) $value->field?->is_searchable)
                ->pluck('value')
                ->filter()
                ->implode(' '),
            'location_path' => $this->locationPath(),
        ];

        // Semantic search (A2): attach a user-provided embedding under the
        // configured embedder name. Skipped (keyword-only) when AI is off or
        // the embedding provider is unavailable — embed() returns null.
        if (($embedder = config('scout.meilisearch.hybrid.embedder'))
            && ($vector = app(ItemEmbedder::class)->embed($this->searchEmbeddingText())) !== null) {
            $document['_vectors'] = [$embedder => $vector];
        }

        return $document;
    }

    /**
     * Compact natural-language summary of the item used to generate its
     * semantic-search embedding. Assumes tags are loaded (see toSearchableArray).
     */
    public function searchEmbeddingText(): string
    {
        return collect([
            $this->name,
            $this->manufacturer ? "by {$this->manufacturer}" : null,
            $this->model_number ? "model {$this->model_number}" : null,
            $this->description,
            $this->tags->isNotEmpty() ? 'Tags: '.$this->tags->pluck('name')->implode(', ') : null,
            $this->locationPath() !== '' ? 'Location: '.$this->locationPath() : null,
        ])->filter()->implode('. ');
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

    /**
     * A sensible default image-search query: maker + name + model number.
     * The description is intentionally left out — long prose hurts image results.
     */
    public function defaultImageSearchQuery(): string
    {
        return collect([$this->manufacturer, $this->name, $this->model_number])
            ->map(fn (?string $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->implode(' ');
    }

    /**
     * Record on the activity log that images were attached to this item. (Images
     * aren't a logged model themselves, so we note the addition against the item.)
     */
    public function logImagesAdded(int $count): void
    {
        if ($count < 1) {
            return;
        }

        activity()
            ->useLog('item')
            ->performedOn($this)
            ->event('image_added')
            ->withProperties(['count' => $count])
            ->log('image_added');
    }
}
