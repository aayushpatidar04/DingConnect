<?php

namespace Database\Factories;

use App\Models\SimOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimOrder>
 */
class SimOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'quantity' => fake()->numberBetween(10, 500),
            'status' => fake()->randomElement(SimOrder::STATUSES),
            'tracking_notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => SimOrder::STATUS_PENDING]);
    }

    public function processing(): static
    {
        return $this->state(['status' => SimOrder::STATUS_PROCESSING]);
    }

    public function dispatched(): static
    {
        return $this->state([
            'status' => SimOrder::STATUS_DISPATCHED,
            'dispatched_at' => now(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => SimOrder::STATUS_DELIVERED,
            'dispatched_at' => now()->subDays(2),
            'delivered_at' => now(),
        ]);
    }
}
