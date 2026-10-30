<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(MenuSeeder::class);
    $this->withCookie(config('session.cookie'), Str::random(40));
});

function cancellablePlacedOrder(array $state = [], bool $card = true): Order
{
    $order = Order::factory()->withLines(2)->live(OrderStatus::Placed)->create($state + [
        'payment_status' => $card ? PaymentStatus::Paid : PaymentStatus::Pending,
    ]);

    if ($card) {
        Payment::factory()->forOrder($order->refresh())->create(['gateway' => 'fake', 'reference' => 'fake_'.Str::random(12)]);
    }

    return $order;
}

it('cancels a placed order for the signed-in owner and refunds the card', function () {
    $user = User::factory()->create();
    $order = cancellablePlacedOrder(['user_id' => $user->id]);

    $this->actingAs($user)->post("/orders/{$order->number}/cancel")
        ->assertRedirect("/orders/{$order->number}");

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($order->payments()->first()->refunded)->toBe($order->total)
        ->and($order->events()->where('to', OrderStatus::Cancelled->value)->where('user_id', $user->id)->exists())->toBeTrue();
});

it('cancels a guest order from the browser that placed it', function () {
    $order = cancellablePlacedOrder();

    $this->withSession(['guest_orders' => [$order->number]])
        ->post("/orders/{$order->number}/cancel")
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded);
});

it('just cancels a pay-at-venue order', function () {
    $order = cancellablePlacedOrder(card: false);

    $this->withSession(['guest_orders' => [$order->number]])
        ->post("/orders/{$order->number}/cancel")
        ->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->payments()->count())->toBe(0);
});

it('refuses once the kitchen has accepted the order', function () {
    $order = Order::factory()->withLines(1)->live(OrderStatus::Accepted)->create();

    $this->withSession(['guest_orders' => [$order->number]])
        ->post("/orders/{$order->number}/cancel")
        ->assertSessionHasErrors('cancel');

    expect($order->refresh()->status)->toBe(OrderStatus::Accepted);
});

it('does not let someone else cancel an order', function () {
    $order = cancellablePlacedOrder(['user_id' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create())->post("/orders/{$order->number}/cancel")->assertForbidden();
    $this->post("/orders/{$order->number}/cancel")->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Placed);
});

it('offers cancel on the tracking page only while placed', function () {
    $user = User::factory()->create();
    $placed = cancellablePlacedOrder(['user_id' => $user->id]);
    $cooking = Order::factory()->withLines(1)->live(OrderStatus::Cooking)->create(['user_id' => $user->id]);

    $this->actingAs($user)->get("/orders/{$placed->number}")
        ->assertInertia(fn (Assert $page) => $page->where('can.cancel', true));
    $this->actingAs($user)->get("/orders/{$cooking->number}")
        ->assertInertia(fn (Assert $page) => $page->where('can.cancel', false));
});

function cancellableBooking(array $state = [], ?DateTimeInterface $at = null): Reservation
{
    return Reservation::factory()->forParty(6)->at($at ?? now()->addDays(3))->create($state);
}

function cancelDepositPayment(Reservation $reservation): Payment
{
    return Payment::factory()->create([
        'order_id' => null,
        'payable_type' => $reservation->getMorphClass(),
        'payable_id' => $reservation->id,
        'gateway' => 'fake',
        'reference' => 'fake_'.Str::random(12),
        'amount' => $reservation->deposit,
    ]);
}

it('cancels a booking before the cut-off and refunds the deposit', function () {
    Event::fake([ReservationUpdated::class]);
    $reservation = cancellableBooking();
    $payment = cancelDepositPayment($reservation);

    $this->withSession(['guest_reservations' => [$reservation->reference]])
        ->post("/book/{$reservation->reference}/cancel")
        ->assertRedirect("/book/{$reservation->reference}");

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->deposit_status)->toBe(PaymentStatus::Refunded)
        ->and($payment->refresh()->refunded)->toBe($payment->amount);
    Event::assertDispatched(ReservationUpdated::class);
});

it('keeps the deposit inside the cut-off but still frees the table', function () {
    $reservation = cancellableBooking(at: now()->addHours(5));
    $payment = cancelDepositPayment($reservation);
    $user = User::factory()->create();
    $reservation->update(['user_id' => $user->id]);

    $this->actingAs($user)->post("/book/{$reservation->reference}/cancel")->assertRedirect();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($reservation->deposit_status)->toBe(PaymentStatus::Paid)
        ->and($payment->refresh()->refunded)->toBe(0);
    $this->assertDatabaseHas('activity_logs', ['event' => 'reservation.cancelled_by_customer']);
});

it('shows the cancellation rule on the booking page', function () {
    $reservation = cancellableBooking(at: now()->addHours(5));

    $this->withSession(['guest_reservations' => [$reservation->reference]])
        ->get("/book/{$reservation->reference}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('cancel.allowed', true)
            ->where('cancel.free', false)
            ->where('cancel.deposit_paid', $reservation->deposit));
});

it('does not let someone else cancel a booking', function () {
    $reservation = cancellableBooking();

    $this->actingAs(User::factory()->create())->post("/book/{$reservation->reference}/cancel")->assertForbidden();
    $this->post("/book/{$reservation->reference}/cancel")->assertForbidden();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('refuses to cancel a booking that is already cancelled', function () {
    $reservation = cancellableBooking(['status' => ReservationStatus::Cancelled]);

    $this->withSession(['guest_reservations' => [$reservation->reference]])
        ->post("/book/{$reservation->reference}/cancel")
        ->assertSessionHasErrors('cancel');
});
