<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MaintenanceEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A completed piece of maintenance on an item. Either the completion of a
 * MaintenanceTask or an ad-hoc record ("repaired the drawer handle"), in
 * which case `maintenance_task_id` is null. Entries are pure history —
 * they are never updated by the scheduling machinery after creation.
 */
class MaintenanceEntry extends Model
{
    /** @use HasFactory<MaintenanceEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'maintenance_task_id',
        'performed_by',
        'completed_at',
        'notes',
        'cost',
    ];

    protected $casts = [
        'completed_at' => 'date',
        'cost' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTask::class, 'maintenance_task_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
