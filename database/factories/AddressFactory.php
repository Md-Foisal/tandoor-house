<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Database\Seeders\Support\EastLondon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            ...EastLondon::address(),
            'instructions' => null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function work(): static
    {
        return $this->state(fn () => ['label' => 'Work']);
    }

    public function inPostcode(string $prefix): static
    {
        return $this->state(fn () => ['postcode' => EastLondon::postcode($prefix)]);
    }
}
