<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ItemImage extends Model
{
    protected $fillable = [
        'item_id',
        'extension',
        'mime_type',
        'width_original',
        'height_original',
        'size_bytes_original',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'bool',
        'sort_order' => 'int',
        'width_original' => 'int',
        'height_original' => 'int',
        'size_bytes_original' => 'int',
    ];

    protected static function booted(): void
    {
        static::saving(function (ItemImage $image): void {
            if ($image->is_primary && $image->item_id) {
                static::query()
                    ->where('item_id', $image->item_id)
                    ->when($image->exists, fn ($q) => $q->where('id', '!=', $image->id))
                    ->where('is_primary', true)
                    ->update(['is_primary' => false]);
            }
        });

        static::deleting(function (ItemImage $image): void {
            Storage::disk('public')->deleteDirectory($image->directory());
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function directory(): string
    {
        return "item-images/{$this->id}";
    }

    public function thumbPath(): string
    {
        return "{$this->directory()}/thumb.{$this->extension}";
    }

    public function largePath(): string
    {
        return "{$this->directory()}/large.{$this->extension}";
    }

    public function originalPath(): string
    {
        return "{$this->directory()}/original.{$this->extension}";
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumbPath());
    }

    public function largeUrl(): string
    {
        return Storage::disk('public')->url($this->largePath());
    }

    public function originalUrl(): string
    {
        return Storage::disk('public')->url($this->originalPath());
    }
}
