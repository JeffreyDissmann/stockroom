<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ad-hoc history entry ("repaired the drawer handle") — maintenance that
 * never had a schedule. Unlike a task completion, the notes are required:
 * without a task title they are the only thing saying what was done.
 */
class StoreMaintenanceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['required', 'string', 'max:2000'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * Model-ready MaintenanceEntry attributes (no task — that is the
     * point of an ad-hoc entry).
     *
     * @return array<string, mixed>
     */
    public function entryAttributes(): array
    {
        $validated = $this->validated();

        return [
            'maintenance_task_id' => null,
            'performed_by' => $this->user()->id,
            'completed_at' => $validated['completed_at'] ?? today(),
            'notes' => $validated['notes'],
            'cost' => $validated['cost'] ?? null,
        ];
    }
}
