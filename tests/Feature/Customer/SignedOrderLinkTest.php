<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Customer\OrderUpdate;
use App\Support\GuestLinks;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(MenuSeeder::class);
    $this->withCookie(config('session.cookie'), Str::random(40));
});

function guestPlacedOrder(): Order
{
    $order = Order::factory()->withLines(2)->live(OrderStatus::Placed)->create([
        'payment_status' => PaymentStatus::Paid,
    ]);
    Payment::factory()->forOrder($order->refresh())->create(['gateway' => 'fake', 'reference' => 'fake_'.Str::random(12)]);

    return $order;
}

it('opens the order from a signed link in a fresh browser', function () {
    $order = guestPlacedOrder();

    $this->get(route('orders.show', $order->number))->assertForbidden();

    $this->get(GuestLinks::order($order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('orders/show')->where('denied', false)->where('number', $order->number));

    $this->get(route('orders.show', $order->number))->assertOk();
});

it('lets the guest cancel after opening a signed link', function () {
    $order = guestPlacedOrder();

    $this->post(route('orders.cancel', $order->number))->assertForbidden();

    $this->get(GuestLinks::order($order))->assertOk();
    $this->post(route('orders.cancel', $order->number))->assertRedirect(route('orders.show', $order->number));

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

it('lets the guest reach the pay page after opening a signed link', function () {
    $order = Order::factory()->withLines(1)->live(OrderStatus::Placed)->create([
        'payment_method' => 'card',
        'payment_status' => PaymentStatus::Pending,
    ]);

    $this->get(route('orders.pay', $order->number))->assertForbidden();

    $this->get(GuestLinks::order($order))->assertOk();
    $this->get(route('orders.pay', $order->number))->assertOk();
});

it('refuses a tampered signed link', function () {
    $order = guestPlacedOrder();
    $other = guestPlacedOrder();
    $link = GuestLinks::order($order);

    $this->get(Str::replace($order->number, $other->number, $link))->assertForbidden();
    $this->get($link.'x')->assertForbidden();
    $this->post(route('orders.cancel', $order->number))->assertForbidden();
});

it('refuses an expired signed link', function () {
    $order = guestPlacedOrder();
    $link = GuestLinks::order($order);

    $this->travel(8)->days();

    $this->get($link)->assertForbidden();
    $this->post(route('orders.cancel', $order->number))->assertForbidden();
});

it('does not give a signed-in stranger access', function () {
    $order = guestPlacedOrder();

    $this->actingAs(User::factory()->create())->get(route('orders.show', $order->number))->assertForbidden();
});

it('puts a signed link in the order update message', function () {
    $order = guestPlacedOrder();

    $text = (new OrderUpdate($order, OrderStatus::Placed))->text();

    expect($text)->toContain('signature=')->toContain("/orders/{$order->number}");
});
