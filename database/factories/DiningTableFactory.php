<?php

namespace Database\Factories;

use App\Enums\TableStatus;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    private static int $slot = 0;

    public function definition(): array
    {
        $slot = self::$slot++;

        return [
            'label' => 'T'.($slot + 1),
            'seats' => fake()->randomElement([2, 4, 4, 6]),
            'area' => 'Main room',
            // Each table gets its own 3 x 3 cell on the 24 x 16 grid, so factory tables never overlap.
            'x' => ($slot % 8) * 3,
            'y' => intdiv($slot % 40, 8) * 3,
            'width' => 2,
            'height' => 2,
            'shape' => fake()->randomElement(['square', 'round', 'rect']),
            'status' => TableStatus::Free,
            'qr_token' => Str::random(32),
        ];
    }

    public function status(TableStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function seated(): static
    {
        return $this->status(TableStatus::Seated);
    }

    public function seats(int $seats): static
    {
        return $this->state(fn () => ['seats' => $seats]);
    }

    public function counter(): static
    {
        return $this->state(fn () => ['area' => 'Tandoor counter', 'seats' => 2, 'width' => 2, 'height' => 1, 'shape' => 'rect']);
    }
}
