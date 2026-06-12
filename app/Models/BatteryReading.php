<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BatteryReadingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single battery-level sample (0-100%) at a point in time, belonging to a
 * battery cycle. Pure history — never mutated after creation.
 */
class BatteryReading extends Model
{
    /** @use HasFactory<BatteryReadingFactory> */
    use HasFactory;

    protected $fillable = [
        'battery_cycle_id',
        'item_id',
        'percent',
        'recorded_at',
    ];

    protected $casts = [
        'percent' => 'integer',
        'recorded_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(BatteryCycle::class, 'battery_cycle_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
