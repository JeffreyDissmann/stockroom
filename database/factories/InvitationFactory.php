<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Invitation::generateToken(),
            'label' => null,
            'created_by' => User::factory(),
            'accepted_by' => null,
            'expires_at' => now()->addDays(Invitation::LIFETIME_DAYS),
            'accepted_at' => null,
        ];
    }

    /**
     * An invite whose link has lapsed.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    /**
     * An invite that has already been redeemed.
     */
    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'accepted_by' => User::factory(),
            'accepted_at' => now(),
        ]);
    }
}
