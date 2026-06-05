<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceEntry>
 */
class MaintenanceEntryFactory extends Factory
{
    /**
     * Default: an ad-hoc history entry (no task) performed by some user.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'maintenance_task_id' => null,
            'performed_by' => User::factory(),
            'completed_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'notes' => fake()->optional()->sentence(),
            'cost' => fake()->optional()->randomFloat(2, 1, 200),
        ];
    }

    /**
     * Attach the entry to a task (and its item, so the pair stays consistent).
     */
    public function forTask(MaintenanceTask $task): static
    {
        return $this->state(fn () => [
            'maintenance_task_id' => $task->id,
            'item_id' => $task->item_id,
        ]);
    }
}
