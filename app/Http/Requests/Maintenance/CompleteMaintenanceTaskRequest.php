<?php

declare(strict_types=1);

namespace App\Http\Requests\Maintenance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * "Mark done" dialog payload. The date may be backdated (you did it last
 * weekend and only record it now) but never lie in the future — completion
 * is a record of something that happened.
 */
class CompleteMaintenanceTaskRequest extends FormRequest
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
            'notes' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ];
    }

    /**
     * Model-ready MaintenanceEntry attributes; the completion date
     * defaults to today when the dialog leaves it untouched.
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
