<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\TipAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipAllocation>
 */
class TipAllocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'period' => now()->startOfWeek()->toDateString(),
            'user_id' => User::factory()->role(Role::Waiter),
            'amount' => fake()->numberBetween(100, 5000),
            'minutes_worked' => fake()->numberBetween(120, 2400),
            'recorded_by' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['paid_at' => now()]);
    }
}
