<?php

namespace Database\Factories;

use App\Enums\LineStatus;
use App\Models\ItemVariant;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A line for a random seeded dish (or a new one if the menu is empty). The
 * order's totals are recalculated after each line is created.
 *
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (OrderLine $line) {
            OrderFactory::recalculate($line->order);
        });
    }

    public function definition(): array
    {
        $item = MenuItem::query()->inRandomOrder()->first() ?? MenuItem::factory()->create();

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => $item->id,
            'item_variant_id' => null,
            'name' => $item->name,
            'variant_name' => null,
            'modifiers' => [],
            'unit_price' => $item->price,
            'quantity' => fake()->numberBetween(1, 3),
            'vat_rate' => $item->vat_rate,
            'station' => $item->station,
            'status' => LineStatus::Queued,
            'notes' => null,
        ];
    }

    public function forItem(MenuItem $item, ?ItemVariant $variant = null): static
    {
        return $this->state(fn () => [
            'menu_item_id' => $item->id,
            'item_variant_id' => $variant?->id,
            'name' => $item->name,
            'variant_name' => $variant?->name,
            'unit_price' => $variant->price ?? $item->price,
            'vat_rate' => $item->vat_rate,
            'station' => $item->station,
        ]);
    }

    public function status(LineStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'started_at' => $status === LineStatus::Queued ? null : now()->subMinutes(6),
            'bumped_at' => $status === LineStatus::Ready ? now()->subMinute() : null,
        ]);
    }

    public function noted(string $notes): static
    {
        return $this->state(fn () => ['notes' => $notes]);
    }
}
