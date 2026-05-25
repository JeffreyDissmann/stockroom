<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word());

        return [
            'name' => $name,
            'type' => CustomFieldType::Text,
            'is_searchable' => false,
            'sort_order' => 0,
            'is_system' => false,
        ];
    }

    public function searchable(): static
    {
        return $this->state(fn (): array => ['is_searchable' => true]);
    }

    public function type(CustomFieldType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function system(string $key): static
    {
        return $this->state(fn (): array => ['key' => $key, 'is_system' => true]);
    }
}
