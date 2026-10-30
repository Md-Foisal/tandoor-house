<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\Gateways\FakeGateway;
use App\Payments\RefundResult;

function venueOrder(array $state = []): Order
{
    $order = Order::factory()->create($state + [
        'payment_method' => PaymentMethod::PayAtVenue,
        'payment_status' => PaymentStatus::Pending,
        'channel' => OrderChannel::DineIn,
        'total' => 2400,
    ]);
    Payment::factory()->forOrder($order)->atVenue()->pending()->create();

    return $order;
}

function paidCardOrder(string $gateway = 'fake'): Order
{
    $order = Order::factory()->create(['payment_method' => PaymentMethod::Card, 'payment_status' => PaymentStatus::Paid, 'total' => 3000]);
    Payment::factory()->forOrder($order)->create(['gateway' => $gateway]);

    return $order;
}

it('lets cashiers, managers and owners take payment on any order', function (Role $role) {
    $order = venueOrder(['channel' => OrderChannel::Pickup]);

    $this->actingAs(User::factory()->role($role)->create())
        ->post("/staff/orders/{$order->number}/payment", ['method' => 'cash'])
        ->assertSessionHasNoErrors();

    $payment = $order->payments()->sole();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->method)->toBe(PaymentMethod::Cash)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(ActivityLog::where('event', 'order.payment_taken')->count())->toBe(1);
})->with([Role::Cashier, Role::Manager, Role::Owner]);

it('lets a waiter take payment on dine-in only', function () {
    $waiter = User::factory()->role(Role::Waiter)->create();
    $pickup = venueOrder(['channel' => OrderChannel::Pickup]);
    $table = venueOrder();

    $this->actingAs($waiter)->post("/staff/orders/{$pickup->number}/payment", ['method' => 'cash'])->assertForbidden();
    $this->post("/staff/orders/{$table->number}/payment", ['method' => 'cash'])->assertSessionHasNoErrors();

    expect($pickup->payments()->sole()->status)->toBe(PaymentStatus::Pending)
        ->and($table->payments()->sole()->status)->toBe(PaymentStatus::Paid);
});

it('keeps kitchen, riders, customers and guests from taking payment', function (Role $role) {
    $order = venueOrder();

    $this->actingAs(User::factory()->role($role)->create())
        ->post("/staff/orders/{$order->number}/payment", ['method' => 'cash'])->assertForbidden();
    expect($order->payments()->sole()->status)->toBe(PaymentStatus::Pending);
})->with([Role::Kitchen, Role::Rider, Role::Customer]);

it('sends guests to log in', function () {
    $this->post('/staff/orders/'.venueOrder()->number.'/payment', ['method' => 'cash'])->assertRedirect('/login');
});

it('records a card on the terminal as a card payment at the venue', function () {
    $order = venueOrder();

    $this->actingAs(User::factory()->role(Role::Cashier)->create())
        ->post("/staff/orders/{$order->number}/payment", ['method' => 'card'])->assertSessionHasNoErrors();

    $payment = $order->payments()->sole();
    expect($payment->gateway)->toBe('venue')
        ->and($payment->method)->toBe(PaymentMethod::Card)
        ->and($payment->meta['tender'])->toBe('terminal')
        ->and($order->refresh()->payment_method)->toBe(PaymentMethod::Card);
});

it('validates the way the guest paid', function () {
    $order = venueOrder();

    $this->actingAs(User::factory()->role(Role::Cashier)->create())
        ->post("/staff/orders/{$order->number}/payment", ['method' => 'bitcoin'])->assertSessionHasErrors('method');
});

it('takes payment only once however often it is sent', function () {
    $order = venueOrder();
    $this->actingAs(User::factory()->role(Role::Cashier)->create());

    $this->post("/staff/orders/{$order->number}/payment", ['method' => 'cash'])->assertSessionHasNoErrors();
    $this->post("/staff/orders/{$order->number}/payment", ['method' => 'card'])->assertSessionHasNoErrors();

    $payment = $order->payments()->sole();
    expect($payment->method)->toBe(PaymentMethod::Cash)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and(ActivityLog::where('event', 'order.payment_taken')->count())->toBe(1);
});

it('refuses payment on an order that has none waiting or was cancelled', function () {
    $paid = paidCardOrder();
    $cancelled = venueOrder(['status' => OrderStatus::Cancelled]);
    $this->actingAs(User::factory()->role(Role::Manager)->create());

    $this->post("/staff/orders/{$paid->number}/payment", ['method' => 'cash'])->assertSessionHasErrors('method');
    $this->post("/staff/orders/{$cancelled->number}/payment", ['method' => 'cash'])->assertForbidden();
});

it('offers take payment only where it is allowed', function () {
    $order = venueOrder();

    $this->actingAs(User::factory()->role(Role::Cashier)->create())->get("/staff/orders/{$order->number}")
        ->assertInertia(fn ($page) => $page->where('abilities.take_payment', true)->where('abilities.payment_due', 2400));

    $this->actingAs(User::factory()->role(Role::Waiter)->create())
        ->get('/staff/orders/'.venueOrder(['channel' => OrderChannel::Delivery])->number)
        ->assertInertia(fn ($page) => $page->where('abilities.take_payment', false)->where('abilities.payment_due', null));
});

it('sends a card refund through the gateway', function () {
    $order = paidCardOrder();

    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->post("/staff/orders/{$order->number}/refund", ['kind' => 'full', 'reason' => 'Cold food'])->assertSessionHasNoErrors();

    $payment = $order->payments()->sole();
    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->meta['refunds'][0]['via'])->toBe('gateway')
        ->and($payment->meta['refunds'][0]['reference'])->toStartWith('fake_re_')
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

it('records a venue refund as cash', function () {
    $order = venueOrder();
    $user = User::factory()->role(Role::Cashier)->create();
    $this->actingAs($user)->post("/staff/orders/{$order->number}/payment", ['method' => 'cash']);

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'amount', 'amount' => 1000, 'reason' => 'Wrong dish'])->assertSessionHasNoErrors();

    expect($order->payments()->sole()->meta['refunds'][0]['via'])->toBe('cash');
});

it('shows a gateway decline as an error and changes nothing', function () {
    app()->instance(FakeGateway::class, new class extends FakeGateway
    {
        public function refund(Payment $payment, int $amount, string $reason): RefundResult
        {
            return new RefundResult(false, message: 'The card issuer declined the refund.');
        }
    });
    $order = paidCardOrder();

    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->post("/staff/orders/{$order->number}/refund", ['kind' => 'full', 'reason' => 'Cold food'])
        ->assertSessionHasErrors(['amount' => 'The card issuer declined the refund.']);

    $payment = $order->payments()->sole();
    expect($payment->refunded)->toBe(0)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and(ActivityLog::where('event', 'order.refunded')->count())->toBe(0);
});

it('keeps what was refunded when a later payment is declined', function () {
    app()->instance(FakeGateway::class, new class extends FakeGateway
    {
        public function refund(Payment $payment, int $amount, string $reason): RefundResult
        {
            return new RefundResult(false, message: 'Declined.');
        }
    });
    $order = venueOrder();
    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->post("/staff/orders/{$order->number}/payment", ['method' => 'cash']);
    $order->payments()->sole()->update(['amount' => 1000]);
    Payment::factory()->forOrder($order)->create(['gateway' => 'fake', 'amount' => 1400]);

    $this->post("/staff/orders/{$order->number}/refund", ['kind' => 'full', 'reason' => 'Walked out'])
        ->assertSessionHasErrors('amount');

    expect($order->payments()->where('gateway', 'venue')->sole()->refunded)->toBe(1000)
        ->and($order->payments()->where('gateway', 'fake')->sole()->refunded)->toBe(0)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and(ActivityLog::where('event', 'order.refunded')->count())->toBe(1);
});
