<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wallet_id' => 'wal_'.fake()->unique()->bothify('########'),
            'amount' => fake()->numberBetween(100, 500_00),
            'currency' => 'USD',
            'status' => PayoutStatus::Pending,
        ];
    }

    public function status(PayoutStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
