<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\Activitylog\Models\Activity;

class ActivityPresenter
{
    /** Friendly labels for the model behind each log entry. */
    private const SUBJECTS = [
        'item' => 'Item',
        'tag' => 'Tag',
        'custom_field' => 'Custom field',
    ];

    /** Friendly labels for logged attribute keys. */
    private const FIELDS = [
        'parent.name' => 'location',
        'is_searchable' => 'searchable',
        'model_number' => 'model number',
        'serial_number' => 'serial number',
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
            'subject_type' => self::SUBJECTS[$activity->log_name] ?? ucfirst((string) $activity->log_name),
            'subject_label' => $activity->subject?->name ?? $attributes['name'] ?? $old['name'] ?? null,
            'subject_url' => $activity->log_name === 'item' && $activity->subject !== null
                ? "/items/{$activity->subject_id}"
                : null,
            'causer' => $activity->causer?->name,
            'changes' => $activity->event === 'updated' ? $this->changes($attributes, $old) : [],
            // For 'image_added' events: how many images were attached.
            'count' => (int) ($activity->properties?->get('count') ?? 0),
            'at' => $activity->created_at?->toIso8601String(),
        ];
    }

    /**
     * Field-level diff for an update, presented with friendly labels and values.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $old
     * @return list<array{field: string, from: string|null, to: string|null}>
     */
    private function changes(array $attributes, array $old): array
    {
        return collect($attributes)
            ->map(fn (mixed $value, string $field): array => [
                'field' => self::FIELDS[$field] ?? str_replace('_', ' ', $field),
                'from' => $this->format($field, $old[$field] ?? null),
                'to' => $this->format($field, $value),
            ])
            ->values()
            ->all();
    }

    private function format(string $field, mixed $value): ?string
    {
        return match (true) {
            $field === 'parent.name' => $value ?? 'Top level',
            $value === null => null,
            is_bool($value) => $value ? 'yes' : 'no',
            default => (string) $value,
        };
    }
}
