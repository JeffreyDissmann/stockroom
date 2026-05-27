<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\Models\Activity;

class ActivityPresenter
{
    /** Friendly labels for the model behind each log entry. */
    private const SUBJECTS = [
        'item' => 'activity.subjects.item',
        'tag' => 'activity.subjects.tag',
        'custom_field' => 'activity.subjects.custom_field',
        'user' => 'activity.subjects.user',
    ];

    /**
     * @return array<string, mixed>
     */
    public function present(Activity $activity): array
    {
        /** @var array<string, mixed> $changes */
        $changes = $activity->attribute_changes?->toArray() ?? [];
        $attributes = (array) ($changes['attributes'] ?? []);
        $old = (array) ($changes['old'] ?? []);

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'subject_type' => isset(self::SUBJECTS[$activity->log_name])
                ? __(self::SUBJECTS[$activity->log_name])
                : ucfirst((string) $activity->log_name),
            'subject_label' => $activity->subject?->name ?? $attributes['name'] ?? $old['name'] ?? null,
            'subject_url' => $activity->log_name === 'item' && $activity->subject !== null
                ? "/items/{$activity->subject_id}"
                : null,
            'causer' => $activity->causer?->name,
            'changes' => $activity->event === 'updated' ? $this->changes($attributes, $old, (string) $activity->log_name) : [],
            // For 'image_added' events: how many images were attached.
            'count' => (int) ($activity->properties?->get('count') ?? 0),
            'at' => $activity->created_at?->toIso8601String(),
        ];
    }

    /**
     * Field-level diff for an update, presented with localized labels and values.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $old
     * @return list<array{field: string, from: string|null, to: string|null}>
     */
    private function changes(array $attributes, array $old, string $logName): array
    {
        return collect($attributes)
            ->map(fn (mixed $value, string $field): array => [
                'field' => $this->fieldLabel($field),
                'from' => $this->format($logName, $field, $old[$field] ?? null),
                'to' => $this->format($logName, $field, $value),
            ])
            ->values()
            ->all();
    }

    private function fieldLabel(string $field): string
    {
        $key = 'activity.fields.'.str_replace('.', '_', $field);

        return Lang::has($key) ? __($key) : str_replace(['.', '_'], ' ', $field);
    }

    private function format(string $logName, string $field, mixed $value): ?string
    {
        if ($field === 'parent.name') {
            return $value ?? __('common.top_level');
        }

        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? __('common.yes') : __('common.no');
        }

        // Enum-typed fields render with their localized label.
        if ($field === 'type') {
            $key = ($logName === 'custom_field' ? 'enums.custom_field_type.' : 'enums.item_type.').$value;

            return Lang::has($key) ? __($key) : (string) $value;
        }

        return (string) $value;
    }
}
