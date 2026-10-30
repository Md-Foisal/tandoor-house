<?php

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderStateMachine;
use Database\Seeders\MenuSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(MenuSeeder::class);
    $this->machine = app(OrderStateMachine::class);
});

function placedOrder(string $channel = 'delivery'): Order
{
    return Order::factory()->{$channel === 'dine_in' ? 'dineIn' : $channel}()->withLines(2)->create([
        'status' => OrderStatus::Placed,
        'accepted_at' => null,
        'ready_at' => null,
        'completed_at' => null,
    ])->fresh();
}

it('walks a delivery order from placed to completed', function () {
    $order = placedOrder();

    foreach ([OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed] as $status) {
        $this->machine->transition($order, $status);
    }

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and($order->accepted_at)->not->toBeNull()
        ->and($order->completed_at)->not->toBeNull()
        ->and($order->events()->where('to', '!=', 'placed')->count())->toBe(6);
});

it('sends dine-in orders to served and pickups to collected', function () {
    $dineIn = placedOrder('dine_in');
    $dineIn->status = OrderStatus::Ready;

    expect($this->machine->nextFor($dineIn))->toBe([OrderStatus::Served]);
});

it('refuses a jump the kitchen cannot make', function () {
    $this->machine->transition(placedOrder(), OrderStatus::Ready);
})->throws(ValidationException::class);

it('needs a reason to reject', function () {
    $this->machine->transition(placedOrder(), OrderStatus::Rejected);
})->throws(ValidationException::class);

it('marks the order ready when the last line is bumped, and recall reopens it', function () {
    $order = placedOrder();
    $this->machine->transition($order, OrderStatus::Accepted);
    [$first, $second] = $order->lines()->get()->all();

    $this->machine->bump($first);
    expect($order->fresh()->status)->toBe(OrderStatus::Cooking);

    $this->machine->bump($second);
    expect($order->fresh()->status)->toBe(OrderStatus::Ready);

    $this->machine->recall($second->fresh());
    expect($order->fresh()->status)->toBe(OrderStatus::Cooking)
        ->and($second->fresh()->status)->toBe(LineStatus::Cooking);
});
