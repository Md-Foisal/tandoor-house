<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->lexify('????')).fake()->numberBetween(5, 50),
            'description' => fake()->sentence(5),
            'percent_off' => 1000,
            'amount_off' => null,
            'minimum_order' => 0,
            'starts_at' => null,
            'ends_at' => null,
            'max_uses' => null,
            'uses' => 0,
            'is_active' => true,
        ];
    }

    /**
     * @param  int  $basisPoints  1000 is 10%
     */
    public function percentOff(int $basisPoints): static
    {
        return $this->state(fn () => ['percent_off' => $basisPoints, 'amount_off' => null]);
    }

    public function amountOff(int $pence, int $minimum = 0): static
    {
        return $this->state(fn () => ['percent_off' => null, 'amount_off' => $pence, 'minimum_order' => $minimum]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['starts_at' => now()->subMonths(4), 'ends_at' => now()->subWeeks(6)]);
    }

    public function exhausted(): static
    {
        return $this->state(fn () => ['max_uses' => 10, 'uses' => 10]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
