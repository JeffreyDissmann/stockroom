<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceScheduleType;
use App\Models\Item;
use App\Models\MaintenanceTask;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceTask>
 */
class MaintenanceTaskFactory extends Factory
{
    /**
     * Default: an interval task ("every N months after completion") due
     * comfortably in the future.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'schedule_type' => MaintenanceScheduleType::Interval,
            'interval_value' => fake()->numberBetween(1, 12),
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'rrule' => null,
            'next_due_at' => today()->addMonth(),
            'last_completed_at' => null,
            'reminder_lead_days' => 7,
            'is_active' => true,
        ];
    }

    public function interval(int $value = 6, MaintenanceIntervalUnit $unit = MaintenanceIntervalUnit::Months): static
    {
        return $this->state(fn () => [
            'schedule_type' => MaintenanceScheduleType::Interval,
            'interval_value' => $value,
            'interval_unit' => $unit,
            'rrule' => null,
        ]);
    }

    public function calendar(string $rrule = 'FREQ=YEARLY;BYMONTH=3;BYDAY=1SU'): static
    {
        return $this->state(fn () => [
            'schedule_type' => MaintenanceScheduleType::Calendar,
            'interval_value' => null,
            'interval_unit' => null,
            'rrule' => $rrule,
        ]);
    }

    public function oneOff(): static
    {
        return $this->state(fn () => [
            'schedule_type' => MaintenanceScheduleType::OneOff,
            'interval_value' => null,
            'interval_unit' => null,
            'rrule' => null,
        ]);
    }

    /**
     * A battery "Replace battery" task whose due date is owned by the
     * depletion forecast (no stored rule columns).
     */
    public function forecast(): static
    {
        return $this->state(fn () => [
            'schedule_type' => MaintenanceScheduleType::Forecast,
            'interval_value' => null,
            'interval_unit' => null,
            'rrule' => null,
        ]);
    }

    public function overdue(int $days = 10): static
    {
        return $this->state(fn () => ['next_due_at' => today()->subDays($days)]);
    }

    public function dueSoon(int $days = 3): static
    {
        return $this->state(fn () => ['next_due_at' => today()->addDays($days)]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false, 'next_due_at' => null]);
    }
}
