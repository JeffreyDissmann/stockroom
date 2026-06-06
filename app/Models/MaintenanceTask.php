<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use Database\Factories\MaintenanceTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A recurring (or one-off) maintenance schedule on an item — "descale every
 * month", "service the heating every October", "replace the filter once".
 *
 * The schedule_type decides which columns drive the recurrence (see the
 * migration). `next_due_at` is a stored projection maintained exclusively by
 * the MaintenanceSchedule service; nothing else may write it, so the digest
 * scan and the due-date sorts can rely on it being in sync with the rule.
 */
class MaintenanceTask extends Model
{
    /** @use HasFactory<MaintenanceTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'title',
        'description',
        'schedule_type',
        'interval_value',
        'interval_unit',
        'rrule',
        'next_due_at',
        'last_completed_at',
        'reminder_lead_days',
        'is_active',
    ];

    protected $casts = [
        'schedule_type' => MaintenanceScheduleType::class,
        'interval_value' => 'int',
        'interval_unit' => MaintenanceIntervalUnit::class,
        'next_due_at' => 'date',
        'last_completed_at' => 'date',
        'reminder_lead_days' => 'int',
        'is_active' => 'bool',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MaintenanceEntry::class)->orderByDesc('completed_at');
    }

    public function latestEntry(): HasOne
    {
        return $this->hasOne(MaintenanceEntry::class)->ofMany('completed_at', 'max');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Strictly past due. Composes with active(): Task::active()->overdue().
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereDate('next_due_at', '<', today());
    }

    /**
     * Due between today and N days from now (inclusive) — upcoming, not
     * yet overdue.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDueWithin(Builder $query, int $days): void
    {
        $query->whereDate('next_due_at', '>=', today())
            ->whereDate('next_due_at', '<=', today()->addDays($days));
    }

    public function isOverdue(): bool
    {
        return $this->is_active
            && $this->next_due_at !== null
            && $this->next_due_at->lt(today());
    }

    /**
     * Days until due: negative when overdue, 0 when due today, null when
     * the task has no due date (archived one-off).
     */
    public function dueInDays(): ?int
    {
        if ($this->next_due_at === null) {
            return null;
        }

        return (int) today()->diffInDays($this->next_due_at, false);
    }

    /**
     * Whether the task has entered its reminder window (due date minus
     * lead days) — the digest's per-task inclusion test.
     */
    public function isWithinReminderWindow(): bool
    {
        return $this->is_active
            && $this->next_due_at !== null
            && today()->gte($this->next_due_at->copy()->subDays($this->reminder_lead_days));
    }

    /**
     * Overdue or inside the reminder window — the single definition of
     * "this task wants the user's attention", shared by the dashboard
     * card, the global page partition and the digest. A PHP predicate
     * (not a query scope) because the per-row window arithmetic isn't
     * portable SQL across pgsql/sqlite.
     */
    public function needsAttention(): bool
    {
        return $this->isOverdue() || $this->isWithinReminderWindow();
    }
}
