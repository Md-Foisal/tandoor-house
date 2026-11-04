<?php

namespace Database\Factories;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\Pricing;
use Database\Seeders\Support\EastLondon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * A pickup order for a guest, with no lines. Add them with withLines(), or
 * create OrderLine rows for the order: totals are recalculated each time.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * The order each channel's order walks through, ending in a completed order.
     *
     * @return list<OrderStatus>
     */
    public static function path(OrderChannel $channel, OrderStatus $to = OrderStatus::Completed): array
    {
        if ($to === OrderStatus::Rejected) {
            return [OrderStatus::Placed, OrderStatus::Rejected];
        }

        if ($to === OrderStatus::Cancelled) {
            return [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Cancelled];
        }

        $path = match ($channel) {
            OrderChannel::Delivery => [OrderStatus::OutForDelivery, OrderStatus::Delivered],
            OrderChannel::Pickup => [OrderStatus::Collected],
            OrderChannel::DineIn => [OrderStatus::Served],
        };

        $path = [OrderStatus::Placed, OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready, ...$path, OrderStatus::Completed];
        $position = array_search($to, $path, true);

        return $position === false ? $path : array_slice($path, 0, $position + 1);
    }

    /**
     * Recompute every money column from the order's lines, zone and tip.
     */
    public static function recalculate(Order $order): void
    {
        $order->load(['lines', 'deliveryZone', 'promoCode']);

        $lines = $order->lines
            ->map(fn (OrderLine $line) => ['total' => $line->unit_price * $line->quantity, 'vat_rate' => $line->vat_rate])
            ->values()
            ->all();

        $order->forceFill(app(Pricing::class)->totals($lines, $order->channel, $order->deliveryZone, $order->promoCode, $order->tip))->save();
    }

    public function definition(): array
    {
        return [
            'number' => 'TH-9'.fake()->unique()->numerify('#####'),
            'channel' => OrderChannel::Pickup,
            'status' => OrderStatus::Placed,
            'user_id' => null,
            'contact' => [
                'name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'phone' => EastLondon::mobile(),
            ],
            'address' => null,
            'postcode' => null,
            'pickup_at' => now()->addMinutes(40),
            'subtotal' => 0,
            'discount' => 0,
            'delivery_fee' => 0,
            'service_charge' => 0,
            'tip' => 0,
            'vat_total' => 0,
            'total' => 0,
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::Card,
            'placed_at' => now(),
            'estimated_ready_at' => now()->addMinutes(30),
        ];
    }

    public function withLines(int $count = 2): static
    {
        return $this->afterCreating(function (Order $order) use ($count) {
            OrderLine::factory()->count($count)->for($order)->create();
        });
    }

    public function withPayment(): static
    {
        return $this->afterCreating(function (Order $order) {
            $factory = Payment::factory()->forOrder($order);

            $factory = in_array($order->payment_method, [PaymentMethod::PayAtVenue, PaymentMethod::Cash], true)
                ? $factory->atVenue($order->payment_method)
                : $factory;

            $factory->state(['status' => $order->payment_status])->create();
        });
    }

    public function forCustomer(User $user): static
    {
        return $this->state(fn () => [
            'user_id' => $user->id,
            'contact' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
        ]);
    }

    public function pickup(): static
    {
        return $this->state(fn () => ['channel' => OrderChannel::Pickup, 'pickup_at' => now()->addMinutes(40)]);
    }

    public function delivery(): static
    {
        return $this->state(fn () => [
            'channel' => OrderChannel::Delivery,
            'delivery_zone_id' => DeliveryZone::factory(),
            'address' => [...EastLondon::address('E2'), 'instructions' => null],
            'postcode' => EastLondon::postcode('E2'),
            'delivery_fee' => 249,
            'pickup_at' => null,
        ]);
    }

    public function dineIn(): static
    {
        return $this->state(fn () => [
            'channel' => OrderChannel::DineIn,
            'dining_table_id' => DiningTable::factory(),
            'payment_method' => PaymentMethod::PayAtVenue,
            'payment_status' => PaymentStatus::Pending,
            'pickup_at' => null,
        ]);
    }

    public function withTip(int $pence = 200): static
    {
        return $this->state(fn () => ['tip' => $pence]);
    }

    public function payAtVenue(): static
    {
        return $this->state(fn () => ['payment_method' => PaymentMethod::PayAtVenue, 'payment_status' => PaymentStatus::Pending]);
    }

    /**
     * Move the order to a status, stamping the timestamps it would have by
     * then and writing one status event for each step.
     */
    public function status(OrderStatus $status): static
    {
        return $this->state(function (array $attributes) use ($status) {
            /** @var Carbon $placed */
            $placed = $attributes['placed_at'];
            $accepted = ! in_array($status, [OrderStatus::Placed, OrderStatus::Rejected], true);
            $cooked = in_array($status, [OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Served, OrderStatus::Collected, OrderStatus::Completed], true);

            return [
                'status' => $status,
                'accepted_at' => $accepted ? $placed->copy()->addMinute() : null,
                'ready_at' => $cooked ? $placed->copy()->addMinutes(22) : null,
                'completed_at' => $status === OrderStatus::Completed ? $placed->copy()->addMinutes(45) : null,
                'rejection_reason' => $status === OrderStatus::Rejected ? 'The kitchen cannot take any more orders tonight.' : null,
            ];
        })->afterCreating(function (Order $order) use ($status) {
            $previous = null;

            foreach (self::path($order->channel, $status) as $index => $step) {
                $order->events()->forceCreate([
                    'from' => $previous?->value,
                    'to' => $step->value,
                    'created_at' => $order->placed_at->copy()->addMinutes($index * 4),
                ]);
                $previous = $step;
            }
        });
    }

    public function completed(): static
    {
        return $this->state(fn () => ['placed_at' => now()->subHours(3), 'payment_status' => PaymentStatus::Paid])
            ->status(OrderStatus::Completed);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['placed_at' => now()->subHours(3)])->status(OrderStatus::Rejected);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['placed_at' => now()->subHours(3)])->status(OrderStatus::Cancelled);
    }

    /**
     * An order the kitchen or floor is working on right now.
     */
    public function live(OrderStatus $status = OrderStatus::Cooking): static
    {
        return $this->state(fn () => ['placed_at' => now()->subMinutes(12)])->status($status);
    }
}
