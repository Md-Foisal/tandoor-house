<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->setTime(17, 0);

        return [
            'user_id' => User::factory()->role(Role::Waiter),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(6),
            'role' => Role::Waiter,
            'notes' => null,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role, 'user_id' => User::factory()->role($role)]);
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn () => ['starts_at' => $start, 'ends_at' => $end]);
    }

    public function onNow(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subHour(), 'ends_at' => now()->addHours(4)]);
    }
}
