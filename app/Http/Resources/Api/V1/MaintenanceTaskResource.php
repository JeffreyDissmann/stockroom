<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\MaintenanceTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A maintenance schedule on an item, in a locale-neutral shape — raw rule
 * fields plus the computed due state Home Assistant needs for sensors. No
 * localized summary string (unlike the web MaintenancePresenter); HA formats
 * its own. `next_due_at` is the stored projection maintained by
 * MaintenanceSchedule.
 *
 * @mixin MaintenanceTask
 */
class MaintenanceTaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'title' => $this->title,
            'description' => $this->description,
            'schedule_type' => $this->schedule_type->value,
            'interval_value' => $this->interval_value,
            'interval_unit' => $this->interval_unit?->value,
            'next_due_at' => $this->next_due_at?->toDateString(),
            'last_completed_at' => $this->last_completed_at?->toDateString(),
            'reminder_lead_days' => $this->reminder_lead_days,
            'is_active' => $this->is_active,
            // Computed due state: negative due_in_days = overdue, 0 = today,
            // null = archived one-off with no upcoming date.
            'due_in_days' => $this->dueInDays(),
            'is_overdue' => $this->isOverdue(),
            'needs_attention' => $this->needsAttention(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
