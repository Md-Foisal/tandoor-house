<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Events\PaymentUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Payments;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

function unpaidOrder(int $total = 2450, PaymentMethod $method = PaymentMethod::Card): Order
{
    return Order::factory()->create([
        'total' => $total,
        'payment_method' => $method,
        'payment_status' => PaymentStatus::Pending,
    ]);
}

it('takes a card payment through the fake gateway after the test form', function () {
    Event::fake([PaymentUpdated::class]);
    $order = unpaidOrder(2450);

    $started = app(Payments::class)->start($order, PaymentMethod::Card);

    expect($started->attempt->status)->toBe(AttemptStatus::RequiresAction)
        ->and($started->attempt->clientSecret)->not->toBeNull()
        ->and($started->payment->reference)->toStartWith('fake_')
        ->and($started->payment->amount)->toBe(2450)
        ->and($started->payment->order_id)->toBe($order->id)
        ->and($started->payment->payable_type)->toBe($order->getMorphClass())
        ->and($started->payment->status)->toBe(PaymentStatus::Pending);

    $payment = app(Payments::class)->confirm($started->payment->reference);

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(PaymentUpdated::class, 1);
});

it('declines fake card amounts ending in 51p', function () {
    Event::fake([PaymentUpdated::class]);
    $order = unpaidOrder(1251);

    $started = app(Payments::class)->start($order, PaymentMethod::Card, ['confirm' => true]);

    expect($started->attempt->status)->toBe(AttemptStatus::Failed)
        ->and($started->payment->status)->toBe(PaymentStatus::Failed)
        ->and($started->payment->meta['failure'])->toBe(__('payments.fake.declined'))
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Failed);
    Event::assertDispatched(PaymentUpdated::class, fn ($e) => $e->payment->is($started->payment) && $e->from === PaymentStatus::Pending);
});

it('gives a retry its own reference after a decline', function () {
    $order = unpaidOrder(1251);
    $payments = app(Payments::class);

    $first = $payments->start($order, PaymentMethod::Card, ['confirm' => true]);
    $second = $payments->start($order, PaymentMethod::Card);

    expect($second->payment->reference)->not->toBe($first->payment->reference)
        ->and(Payment::count())->toBe(2);
});

it('never takes the amount from the caller and refuses a paid payable', function () {
    $order = Order::factory()->create(['total' => 3000, 'payment_status' => PaymentStatus::Paid]);

    app(Payments::class)->start($order, PaymentMethod::Card);
})->throws(PaymentException::class);

it('only pays once when the same payment is confirmed twice', function () {
    Event::fake([PaymentUpdated::class]);
    $order = unpaidOrder(1800);
    $payments = app(Payments::class);
    $started = $payments->start($order, PaymentMethod::Card);

    $stale = Payment::find($started->payment->id);
    $payments->confirm($started->payment->reference);
    $payments->confirm($stale->reference);

    expect(Payment::sole()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(PaymentUpdated::class, 1);
});

it('keeps pay at venue pending until staff take it', function () {
    Event::fake([PaymentUpdated::class]);
    $order = unpaidOrder(2200, PaymentMethod::PayAtVenue);
    $waiter = User::factory()->role(Role::Waiter)->create();
    $payments = app(Payments::class);

    $started = $payments->start($order, PaymentMethod::PayAtVenue);

    expect($started->attempt->status)->toBe(AttemptStatus::Pending)
        ->and($started->payment->gateway)->toBe('venue')
        ->and($payments->confirm($started->payment->reference)->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);

    $payment = $payments->takeAtVenue($started->payment, $waiter);
    $payments->takeAtVenue($started->payment, $waiter);

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->meta['taken_by_id'])->toBe($waiter->id)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(PaymentUpdated::class, 1);
});

it('only lets staff take venue payments', function () {
    $order = unpaidOrder(2000);
    $started = app(Payments::class)->start($order, PaymentMethod::Card);

    app(Payments::class)->takeAtVenue($started->payment, User::factory()->role(Role::Waiter)->create());
})->throws(ValidationException::class);

it('refunds part then the rest, keeping the refund history on the row', function () {
    Event::fake([PaymentUpdated::class]);
    $order = unpaidOrder(3000);
    $manager = User::factory()->role(Role::Manager)->create();
    $payments = app(Payments::class);
    $payment = $payments->start($order, PaymentMethod::Card, ['confirm' => true])->payment;

    $payments->refund($payment, 1000, 'Cold naan', $manager, [7 => 1]);

    expect($payment->refunded)->toBe(1000)
        ->and($payment->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->meta['refunds'][0])->toMatchArray(['amount' => 1000, 'reason' => 'Cold naan', 'by' => $manager->name, 'lines' => [7 => 1], 'via' => 'gateway'])
        ->and($payment->meta['refunds'][0]['reference'])->toStartWith('fake_re_')
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);

    $payments->refund($payment, 2000, 'Order was late', $manager);

    expect($payment->status)->toBe(PaymentStatus::Refunded)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
    Event::assertDispatchedTimes(PaymentUpdated::class, 3);
});

it('rejects a refund over what is left', function () {
    $order = unpaidOrder(3000);
    $manager = User::factory()->role(Role::Manager)->create();
    $payments = app(Payments::class);
    $payment = $payments->start($order, PaymentMethod::Card, ['confirm' => true])->payment;
    $payments->refund($payment, 2500, 'Wrong dish', $manager);

    expect(fn () => $payments->refund($payment, 501, 'Again', $manager))
        ->toThrow(ValidationException::class);

    expect($payment->fresh()->refunded)->toBe(2500);
});

it('refuses to refund a payment that was never paid', function () {
    $order = unpaidOrder(3000, PaymentMethod::PayAtVenue);
    $started = app(Payments::class)->start($order, PaymentMethod::PayAtVenue);

    app(Payments::class)->refund($started->payment, 100, 'Nope', User::factory()->role(Role::Manager)->create());
})->throws(ValidationException::class);

it('records a cash refund for venue payments', function () {
    $order = unpaidOrder(2000, PaymentMethod::Cash);
    $cashier = User::factory()->role(Role::Cashier)->create();
    $payments = app(Payments::class);
    $payment = $payments->takeAtVenue($payments->start($order, PaymentMethod::Cash)->payment, $cashier);

    $payments->refund($payment, 2000, 'Changed their mind', $cashier);

    expect($payment->meta['refunds'][0]['via'])->toBe('cash')
        ->and($payment->status)->toBe(PaymentStatus::Refunded);
});

it('takes a booking deposit against the reservation', function () {
    $reservation = Reservation::factory()->pendingDeposit(8)->create();

    $started = app(Payments::class)->start($reservation, PaymentMethod::Card, ['confirm' => true]);

    expect($started->payment->amount)->toBe($reservation->deposit)
        ->and($started->payment->order_id)->toBeNull()
        ->and($reservation->payments()->sole()->is($started->payment))->toBeTrue()
        ->and($reservation->fresh()->deposit_status)->toBe(PaymentStatus::Paid);
});

it('runs the real event path synchronously', function () {
    $seen = [];
    Event::listen(PaymentUpdated::class, function (PaymentUpdated $event) use (&$seen) {
        $seen[] = [$event->from, $event->payment->status];
    });

    app(Payments::class)->start(unpaidOrder(900), PaymentMethod::Card, ['confirm' => true]);

    expect($seen)->toBe([[PaymentStatus::Pending, PaymentStatus::Paid]]);
});
