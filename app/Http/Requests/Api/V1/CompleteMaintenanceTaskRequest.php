<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mark a maintenance task done via the API. Mirrors the web completion
 * request: the date may be backdated but never lie in the future, and a
 * history entry is recorded with the cost/notes. The performer is the
 * token's user.
 */
class CompleteMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'completed_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * Model-ready MaintenanceEntry attributes; completion date defaults to
     * today, performer is the authenticated token's user.
     *
     * @return array<string, mixed>
     */
    public function entryAttributes(): array
    {
        $validated = $this->validated();

        return [
            'performed_by' => $this->user()->id,
            'completed_at' => $validated['completed_at'] ?? today(),
            'notes' => $validated['notes'] ?? null,
            'cost' => $validated['cost'] ?? null,
        ];
    }
}
