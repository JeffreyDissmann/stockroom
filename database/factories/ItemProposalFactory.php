<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProposalField;
use App\Enums\ProposalStatus;
use App\Models\Item;
use App\Models\ItemProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ItemProposal> */
class ItemProposalFactory extends Factory
{
    protected $model = ItemProposal::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'field' => ProposalField::Description,
            'current_value' => null,
            'proposed_value' => $this->faker->sentence(),
            'status' => ProposalStatus::Pending,
            'source_image_ids' => [1],
            'model' => 'ministral-3:8b',
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => ProposalStatus::Accepted,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ProposalStatus::Rejected,
            'reviewed_at' => now(),
        ]);
    }

    public function forField(ProposalField $field, string $proposed): static
    {
        return $this->state(fn (): array => [
            'field' => $field,
            'proposed_value' => $proposed,
        ]);
    }
}
