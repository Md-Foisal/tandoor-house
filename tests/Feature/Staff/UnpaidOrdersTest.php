<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

it('keeps card orders off the board until they are paid', function () {
    $unpaid = Order::factory()->create(['status' => OrderStatus::Placed, 'payment_method' => PaymentMethod::Card, 'payment_status' => PaymentStatus::Pending, 'placed_at' => now()]);
    $paid = Order::factory()->create(['status' => OrderStatus::Placed, 'payment_method' => PaymentMethod::Card, 'payment_status' => PaymentStatus::Paid, 'placed_at' => now()]);
    $venue = Order::factory()->create(['status' => OrderStatus::Placed, 'payment_method' => PaymentMethod::PayAtVenue, 'payment_status' => PaymentStatus::Pending, 'placed_at' => now()]);

    $numbers = collect(Order::query()->releasedToStaff()->pluck('number'));

    expect($numbers)->not->toContain($unpaid->number)
        ->and($numbers)->toContain($paid->number)
        ->and($numbers)->toContain($venue->number);

    $this->actingAs(User::factory()->role(Role::Cashier)->create())
        ->get('/staff/orders')
        ->assertInertia(fn ($page) => $page->where('orders', fn ($orders) => collect($orders)->pluck('number')->doesntContain($unpaid->number)
            && collect($orders)->pluck('number')->contains($paid->number)));
});
