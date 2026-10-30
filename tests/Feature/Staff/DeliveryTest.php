<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function rider(): User
{
    return User::factory()->role(Role::Rider)->create();
}

function run(?User $rider, OrderStatus $status = OrderStatus::Ready, array $state = []): Order
{
    $order = Order::factory()->delivery()->withPayment()->live($status)->create([...$state, 'rider_id' => $rider?->id]);
    OrderLine::factory()->for($order)->forItem(MenuItem::factory()->create(['price' => 1000]))->create(['quantity' => 2]);

    return $order->refresh();
}

function cashRun(?User $rider, OrderStatus $status = OrderStatus::OutForDelivery): Order
{
    return run($rider, $status, ['payment_method' => PaymentMethod::Cash, 'payment_status' => PaymentStatus::Pending]);
}

it('lets riders, managers and owners in and nobody else', function (Role $role, int $status) {
    $this->actingAs(User::factory()->role($role)->create())->get('/staff/deliveries')->assertStatus($status);
})->with([
    [Role::Rider, 200], [Role::Manager, 200], [Role::Owner, 200],
    [Role::Kitchen, 403], [Role::Waiter, 403], [Role::Cashier, 403], [Role::Customer, 403],
]);

it('shows a rider only their own runs', function () {
    $me = rider();
    $mine = run($me);
    run(rider());
    run(null);

    $this->actingAs($me)->get('/staff/deliveries')
        ->assertInertia(fn ($page) => $page
            ->component('staff/deliveries/index')
            ->has('deliveries.ready', 1)
            ->where('deliveries.ready.0.number', $mine->number)
            ->where('manages', false));
});

it('shows managers every run plus unassigned ones', function () {
    run(rider());
    run(rider(), OrderStatus::OutForDelivery);
    run(null);

    $this->actingAs(User::factory()->role(Role::Manager)->create())->get('/staff/deliveries')
        ->assertInertia(fn ($page) => $page->has('deliveries.ready', 2)->has('deliveries.road', 1)->where('manages', true)->has('riders'));
});

it('refuses another rider on every action', function () {
    $other = run(rider(), OrderStatus::OutForDelivery);
    $ready = run(rider());
    $this->actingAs(rider());

    $this->post("/staff/deliveries/{$ready->number}/pickup")->assertForbidden();
    $this->post("/staff/deliveries/{$other->number}/deliver")->assertForbidden();
    $this->post("/staff/deliveries/{$other->number}/fail", ['reason' => 'No answer'])->assertForbidden();
    $this->patch("/staff/deliveries/{$ready->number}/rider", ['rider_id' => null])->assertForbidden();
    expect($ready->refresh()->status)->toBe(OrderStatus::Ready);
});

it('keeps kitchen, waiters and customers off the actions', function (Role $role) {
    $order = run(null);
    $this->actingAs(User::factory()->role($role)->create());

    $this->post("/staff/deliveries/{$order->number}/pickup")->assertForbidden();
    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
})->with([Role::Kitchen, Role::Waiter, Role::Customer]);

it('lets a manager assign and reassign a rider', function () {
    $a = rider();
    $b = rider();
    $order = run(null);
    $manager = User::factory()->role(Role::Manager)->create();

    $this->actingAs($manager)->patch("/staff/deliveries/{$order->number}/rider", ['rider_id' => $a->id])->assertRedirect();
    expect($order->refresh()->rider_id)->toBe($a->id);

    $this->patch("/staff/deliveries/{$order->number}/rider", ['rider_id' => $b->id])->assertRedirect();
    expect($order->refresh()->rider_id)->toBe($b->id)
        ->and(ActivityLog::where('event', 'order.rider')->count())->toBe(2);
});

it('does not let a rider be assigned who is not a rider', function () {
    $order = run(null);

    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->patch("/staff/deliveries/{$order->number}/rider", ['rider_id' => User::factory()->role(Role::Waiter)->create()->id])
        ->assertSessionHasErrors('rider_id');
});

it('picks up an order and puts it on the road', function () {
    $me = rider();
    $order = run($me);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/pickup")->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery);
});

it('delivers a card order without asking for cash', function () {
    $me = rider();
    $order = run($me, OrderStatus::OutForDelivery);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver")->assertRedirect()->assertSessionHasNoErrors();
    expect($order->refresh()->status)->toBe(OrderStatus::Delivered)->and($order->cash_collected)->toBeNull();

    $other = run($me, OrderStatus::OutForDelivery);
    $this->post("/staff/deliveries/{$other->number}/deliver", ['cash_collected' => 1000])->assertSessionHasErrors('cash_collected');
    expect($other->refresh()->status)->toBe(OrderStatus::OutForDelivery);
});

it('requires the cash amount for a cash order and records it', function () {
    $me = rider();
    $order = cashRun($me);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver")->assertSessionHasErrors('cash_collected');
    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery);

    $this->post("/staff/deliveries/{$order->number}/deliver", ['cash_collected' => $order->total])->assertRedirect()->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Delivered)
        ->and($order->cash_collected)->toBe($order->total)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($order->payment_method)->toBe(PaymentMethod::Cash);

    $payment = $order->payments()->sole();
    expect($payment->method)->toBe(PaymentMethod::Cash)
        ->and($payment->gateway)->toBe('venue')
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount)->toBe($order->total);
});

it('records the order total as the payment and the change in meta', function () {
    $me = rider();
    $order = cashRun($me);
    $tendered = $order->total + 500;

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver", ['cash_collected' => $tendered])->assertSessionHasNoErrors();

    $payment = $order->refresh()->payments()->sole();
    expect($order->cash_collected)->toBe($tendered)
        ->and($payment->amount)->toBe($order->total)
        ->and($payment->meta)->toMatchArray(['cash_tendered' => $tendered, 'change_given' => 500]);
});

it('does not let a rider mark a cash order paid while short', function () {
    $me = rider();
    $order = cashRun($me);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver", ['cash_collected' => $order->total - 1])
        ->assertSessionHasErrors('cash_collected');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::OutForDelivery)
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->cash_collected)->toBeNull();
});

it('refuses a second delivery of the same order', function () {
    $me = rider();
    $order = run($me, OrderStatus::OutForDelivery);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver")->assertSessionHasNoErrors();
    $this->post("/staff/deliveries/{$order->number}/deliver")->assertSessionHasErrors('status');
});

it('only lets managers and owners assign riders, and only on live runs', function () {
    $order = run(null);
    $this->actingAs(rider())->patch("/staff/deliveries/{$order->number}/rider", ['rider_id' => rider()->id])->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->patch("/staff/deliveries/{$order->number}/rider", ['rider_id' => rider()->id])->assertRedirect();

    $done = run(null, OrderStatus::Delivered);
    $this->patch("/staff/deliveries/{$done->number}/rider", ['rider_id' => rider()->id])->assertSessionHasErrors('rider_id');
});

it('totals the cash taken today for the rider', function () {
    $me = rider();
    $order = cashRun($me);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver", ['cash_collected' => 2500]);

    $this->get('/staff/deliveries?tab=done')
        ->assertInertia(fn ($page) => $page->where('cash.total', 2500)->has('deliveries.done', 1));
});

it('logs a failed delivery, keeps the order on the road and flags it on the card', function () {
    $me = rider();
    $order = run($me, OrderStatus::OutForDelivery);

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/fail", [])->assertSessionHasErrors('reason');
    $this->post("/staff/deliveries/{$order->number}/fail", ['reason' => 'No answer at the door'])->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery)
        ->and(ActivityLog::where('event', 'delivery.failed')->where('subject_id', $order->id)->exists())->toBeTrue();

    $this->get('/staff/deliveries')->assertInertia(fn ($page) => $page->where('deliveries.road.0.last_failure', 'No answer at the door'));
});

it('flags orders with alcohol for an age check', function () {
    $me = rider();
    $order = run($me);
    OrderLine::factory()->for($order)->forItem(MenuItem::factory()->create(['is_alcoholic' => true]))->create();

    $this->actingAs($me)->get('/staff/deliveries')->assertInertia(fn ($page) => $page->where('deliveries.ready.0.age_check', true));
});

it('refuses a pickup from a rider removed from the run after authorisation', function () {
    $me = rider();
    $order = run($me);
    $other = rider();
    $swapped = false;
    Order::retrieved(function (Order $o) use (&$swapped, $other) {
        if (! $swapped) {
            $swapped = true;
            DB::table('orders')->where('id', $o->id)->update(['rider_id' => $other->id]);
        }
    });

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/pickup")->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('refuses to record delivery or cash from a rider removed from the run', function () {
    $me = rider();
    $order = cashRun($me);
    $other = rider();
    $swapped = false;
    Order::retrieved(function (Order $o) use (&$swapped, $other) {
        if (! $swapped) {
            $swapped = true;
            DB::table('orders')->where('id', $o->id)->update(['rider_id' => $other->id]);
        }
    });

    $this->actingAs($me)->post("/staff/deliveries/{$order->number}/deliver", ['cash_collected' => $order->total])->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::OutForDelivery)
        ->and($order->cash_collected)->toBeNull();
});

it('will not send an order out for delivery without a rider', function () {
    $order = run(null);

    $this->actingAs(User::factory()->role(Role::Manager)->create())
        ->post("/staff/deliveries/{$order->number}/pickup")
        ->assertSessionHasErrors('status');

    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});
