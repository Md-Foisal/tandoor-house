<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Zone '.fake()->unique()->numberBetween(1, 999),
            'postcode_prefixes' => ['E1', 'E2'],
            'fee' => 249,
            'minimum_order' => 1500,
            'eta_minutes' => 25,
            'is_active' => true,
        ];
    }

    /**
     * @param  list<string>  $prefixes
     */
    public function covering(array $prefixes): static
    {
        return $this->state(fn () => ['postcode_prefixes' => $prefixes]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
