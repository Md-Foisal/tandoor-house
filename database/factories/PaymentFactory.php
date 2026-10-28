<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'gateway' => 'stripe',
            'reference' => 'pi_3'.Str::random(21),
            'method' => PaymentMethod::Card,
            'amount' => fake()->numberBetween(1500, 7500),
            'refunded' => 0,
            'status' => PaymentStatus::Paid,
            'meta' => ['brand' => 'visa', 'last4' => fake()->numerify('####')],
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn () => ['order_id' => $order->id, 'amount' => $order->total]);
    }

    public function atVenue(PaymentMethod $method = PaymentMethod::PayAtVenue): static
    {
        return $this->state(fn () => ['gateway' => 'venue', 'reference' => null, 'method' => $method, 'meta' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Pending]);
    }

    public function refunded(?int $pence = null): static
    {
        return $this->state(fn (array $attributes) => [
            'refunded' => $pence ?? $attributes['amount'],
            'status' => $pence === null || $pence >= $attributes['amount'] ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded,
        ]);
    }
}
