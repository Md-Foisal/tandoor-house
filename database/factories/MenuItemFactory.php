<?php

namespace Database\Factories;

use App\Enums\Allergen;
use App\Enums\Station;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->word().' '.fake()->word().' '.fake()->word());

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'price' => fake()->numberBetween(4, 24) * 50,
            'kcal' => fake()->numberBetween(120, 900),
            'spice_level' => fake()->numberBetween(0, 3),
            'is_vegetarian' => false,
            'is_vegan' => false,
            'allergens' => fake()->randomElements(Allergen::cases(), fake()->numberBetween(0, 3)),
            'station' => fake()->randomElement(Station::cases()),
            'prep_minutes' => fake()->numberBetween(6, 20),
            'is_available' => true,
        ];
    }

    public function vegan(): static
    {
        return $this->state(fn () => ['is_vegetarian' => true, 'is_vegan' => true]);
    }

    public function soldOut(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }
}
