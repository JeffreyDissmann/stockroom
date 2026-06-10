<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BatteryCycleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical battery installed in an item. The open cycle (removed_at
 * null) is the current battery; closed cycles are history. Readings hang
 * off a cycle, so the depletion chart draws one line per battery.
 */
class BatteryCycle extends Model
{
    /** @use HasFactory<BatteryCycleFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'installed_at',
        'removed_at',
        'notes',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Samples for this battery, oldest first — the order the chart and the
     * forecast regression both want.
     */
    public function readings(): HasMany
    {
        return $this->hasMany(BatteryReading::class)->orderBy('recorded_at');
    }

    public function latestReading(): HasOne
    {
        return $this->hasOne(BatteryReading::class)->latestOfMany('recorded_at');
    }

    public function isOpen(): bool
    {
        return $this->removed_at === null;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('removed_at');
    }
}
