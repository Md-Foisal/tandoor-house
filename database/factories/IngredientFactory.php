<?php

namespace Database\Factories;

use App\Models\Ingredient;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => fake()->unique()->words(2, true),
            'unit' => 'g',
            'stock' => 8000,
            'low_threshold' => 2000,
            'par_level' => 10000,
            'cost_per_thousand' => fake()->numberBetween(150, 2500),
        ];
    }

    public function low(): static
    {
        return $this->state(fn (array $attributes) => ['stock' => (int) ($attributes['low_threshold'] * 0.5)]);
    }

    public function unit(string $unit): static
    {
        return $this->state(fn () => ['unit' => $unit]);
    }
}
