<?php

use App\Actions\ConfirmBookingDeposit;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\TableBillPayment;
use App\Models\User;
use App\Payments\AttemptStatus;
use App\Payments\Payments;
use App\Payments\WebhookOutcome;
use App\Policies\ReservationPolicy;
use App\Services\ReservationCancellation;
use App\Services\TableAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->travelTo(CarbonImmutable::parse('2030-03-04 18:00', 'Europe/London'));
});

function safetyOrder(int $total = 2450, array $overrides = []): Order
{
    return Order::factory()->create([
        'total' => $total,
        'status' => OrderStatus::Placed,
        'channel' => OrderChannel::Pickup,
        'payment_method' => PaymentMethod::Card,
        'payment_status' => PaymentStatus::Pending,
        ...$overrides,
    ]);
}

/** What the fake gateway's webhook would say if it sent one. */
function landed(Payment $payment, AttemptStatus $status = AttemptStatus::Succeeded): bool
{
    $payments = app(Payments::class);

    return $payments->applyWebhook($payments->gateway($payment->gateway), new WebhookOutcome(
        (string) $payment->reference,
        $status,
        $status === AttemptStatus::Succeeded ? $payment->amount : null,
    ));
}

function startShare(DiningTable $table, array $data): TableBillPayment
{
    test()->post(route('table.pay.store', $table->qr_token), $data)->assertSessionHasNoErrors();
    $share = TableBillPayment::query()->latest('id')->firstOrFail();
    test()->get(route('table.pay.show', [$table->qr_token, $share]))->assertOk();

    return $share;
}

function autoRefundLogged(string $why): bool
{
    return ActivityLog::query()->where('event', 'payment.auto_refunded')->whereNull('user_id')
        ->get()->contains(fn (ActivityLog $log) => ($log->properties['why'] ?? null) === $why);
}

it('cancels a declined attempt when a new one starts, so an old tab cannot charge twice', function () {
    $order = safetyOrder();
    $payments = app(Payments::class);
    $first = $payments->start($order, PaymentMethod::Card)->payment;
    landed($first, AttemptStatus::Failed);

    $second = $payments->start($order->refresh(), PaymentMethod::Card)->payment;

    expect($first->refresh()->meta['cancelled'])->toBeTrue()
        ->and($first->status)->toBe(PaymentStatus::Failed);

    expect($payments->confirm((string) $first->reference)->status)->toBe(PaymentStatus::Failed)
        ->and($payments->confirm((string) $second->reference)->status)->toBe(PaymentStatus::Paid)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('refunds the second payment in full when an old attempt lands after the order was paid', function () {
    $order = safetyOrder();
    $payments = app(Payments::class);
    $first = $payments->start($order, PaymentMethod::Card)->payment;
    landed($first, AttemptStatus::Failed);
    $second = $payments->start($order->refresh(), PaymentMethod::Card)->payment;
    $payments->confirm((string) $second->reference);

    expect(landed($first))->toBeTrue();

    $first->refresh();
    expect($first->status)->toBe(PaymentStatus::Refunded)
        ->and($first->refunded)->toBe(2450)
        ->and($first->meta['auto_refund'])->toBe('duplicate')
        ->and($first->meta['refunds'][0]['automated'])->toBeTrue()
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($second->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and(autoRefundLogged('duplicate'))->toBeTrue();

    expect(landed($first))->toBeFalse();
});

it('cancels the card attempt when a pickup order switches to paying at the restaurant', function () {
    $customer = User::factory()->create();
    $order = safetyOrder(2450, ['user_id' => $customer->id]);
    $card = app(Payments::class)->start($order, PaymentMethod::Card)->payment;

    $this->actingAs($customer)->post(route('orders.pay.venue', $order->number))->assertRedirect();

    expect($card->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($card->meta['cancelled'])->toBeTrue()
        ->and(app(Payments::class)->confirm((string) $card->reference)->status)->toBe(PaymentStatus::Failed)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('keeps a card payment that lands after the switch and stops the till taking it again', function () {
    $customer = User::factory()->create();
    $waiter = User::factory()->role(Role::Waiter)->create();
    $order = safetyOrder(2450, ['user_id' => $customer->id]);
    $card = app(Payments::class)->start($order, PaymentMethod::Card)->payment;
    $this->actingAs($customer)->post(route('orders.pay.venue', $order->number));
    $venue = $order->payments()->where('gateway', 'venue')->sole();

    landed($card);

    expect($card->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($venue->refresh()->meta['cancelled'])->toBeTrue();

    expect(fn () => app(Payments::class)->takeAtVenue($venue, $waiter))->toThrow(ValidationException::class);
});

it('refuses to take a venue payment for something already paid', function () {
    $order = safetyOrder(2200, ['payment_method' => PaymentMethod::PayAtVenue]);
    $venue = app(Payments::class)->start($order, PaymentMethod::PayAtVenue)->payment;
    $order->forceFill(['payment_status' => PaymentStatus::Paid])->save();

    expect(fn () => app(Payments::class)->takeAtVenue($venue, User::factory()->role(Role::Waiter)->create()))
        ->toThrow(ValidationException::class, __('payments.venue.already_paid'));

    expect($venue->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('cancels the open card attempt when the customer cancels, and refunds one that lands late', function () {
    $customer = User::factory()->create();
    $order = safetyOrder(1800, ['user_id' => $customer->id]);
    $card = app(Payments::class)->start($order, PaymentMethod::Card)->payment;

    $this->actingAs($customer)->post(route('orders.cancel', $order->number))->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($card->refresh()->meta['cancelled'])->toBeTrue();

    landed($card);

    expect($card->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($card->meta['auto_refund'])->toBe('cancelled')
        ->and($order->refresh()->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and(autoRefundLogged('cancelled'))->toBeTrue();
});

it('refunds a deposit that lands after the booking was cancelled', function () {
    $reservation = Reservation::factory()->pendingDeposit(8)->at('2030-03-10 19:00')->create();
    $payment = app(Payments::class)->start($reservation, PaymentMethod::Card)->payment;

    app(ReservationCancellation::class)->cancel($reservation, null);

    expect($payment->refresh()->meta['cancelled'])->toBeTrue();

    landed($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->meta['auto_refund'])->toBe('cancelled')
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled);
});

it('frees the table once an unpaid deposit hold runs out', function () {
    $table = DiningTable::factory()->create(['seats' => 8]);
    $start = CarbonImmutable::parse('2030-03-10 19:00', 'Europe/London');
    Reservation::factory()->pendingDeposit(8)->at($start)->create(['dining_table_id' => $table->id]);
    $availability = app(TableAvailability::class);

    expect($availability->findTable($start, 8)?->id)->not->toBe($table->id);

    $this->travel(20)->minutes();

    expect($availability->findTable($start, 8)?->id)->toBe($table->id);
});

it('keeps the table for a paid deposit even after the hold time', function () {
    $table = DiningTable::factory()->create(['seats' => 8]);
    $start = CarbonImmutable::parse('2030-03-10 19:00', 'Europe/London');
    Reservation::factory()->pendingDeposit(8)->at($start)->create(['dining_table_id' => $table->id, 'deposit_status' => PaymentStatus::Paid]);

    $this->travel(20)->minutes();

    expect(app(TableAvailability::class)->findTable($start, 8)?->id)->not->toBe($table->id);
});

it('will not confirm a booking or keep a deposit once the hold has run out', function () {
    $reservation = Reservation::factory()->pendingDeposit(8)->at('2030-03-10 19:00')->create();
    $payment = app(Payments::class)->start($reservation, PaymentMethod::Card)->payment;

    $this->travel(20)->minutes();

    expect(app(ConfirmBookingDeposit::class)->handle($reservation))->toBeFalse();

    $this->withSession([ReservationPolicy::SESSION_KEY => [$reservation->reference]])
        ->postJson(route('book.pay.confirm', $reservation), ['reference' => $payment->reference, 'token' => 'tok_visa'])
        ->assertJson(['status' => 'failed', 'message' => __('payments.hold_expired')]);

    landed($payment);

    expect($payment->refresh()->status)->not->toBe(PaymentStatus::Paid)
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::PendingDeposit)
        ->and($reservation->deposit_status)->not->toBe(PaymentStatus::Paid);
});

it('refunds a deposit that lands after the hold ran out', function () {
    $reservation = Reservation::factory()->pendingDeposit(8)->at('2030-03-10 19:00')->create();
    $payment = app(Payments::class)->start($reservation, PaymentMethod::Card)->payment;

    $this->travel(20)->minutes();
    landed($payment);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->meta['auto_refund'])->toBe('expired')
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::PendingDeposit)
        ->and(autoRefundLogged('expired'))->toBeTrue();
});

it('counts only what is left of a partly refunded deposit as held', function () {
    $reservation = Reservation::factory()->pendingDeposit(8)->at('2030-03-10 19:00')->create();
    $payment = app(Payments::class)->start($reservation, PaymentMethod::Card, ['confirm' => true])->payment;
    app(Payments::class)->refund($payment, 3000, 'Smaller party', User::factory()->role(Role::Manager)->create());

    $terms = app(ReservationCancellation::class)->terms($reservation->refresh(), true);

    expect($terms['deposit_paid'])->toBe($reservation->deposit - 3000);
});

describe('table shares', function () {
    beforeEach(function () {
        $this->travelTo(CarbonImmutable::parse('2030-03-04 19:00', 'Europe/London'));
        $this->table = DiningTable::factory()->status(TableStatus::Seated)->create();
        $this->order = Order::factory()->create([
            'channel' => OrderChannel::DineIn,
            'dining_table_id' => $this->table->id,
            'payment_method' => PaymentMethod::PayAtVenue,
            'payment_status' => PaymentStatus::Pending,
            'subtotal' => 1800,
            'service_charge' => 200,
            'total' => 2000,
            'placed_at' => now(),
        ]);
    });

    it('refunds the part of a lapsed share that the table no longer owed', function () {
        $lapsed = startShare($this->table, ['amount' => 1200]);
        $this->travel(20)->minutes();
        $second = startShare($this->table, ['amount' => 1200]);
        app(Payments::class)->confirm((string) $second->payments()->sole()->reference);

        $payment = $lapsed->payments()->sole();
        landed($payment);

        expect((int) DB::table('table_bill_payment_orders')->sum('amount'))->toBe(2000)
            ->and($this->order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
            ->and($payment->refresh()->refunded)->toBe(400)
            ->and($payment->status)->toBe(PaymentStatus::PartiallyRefunded)
            ->and($payment->meta['auto_refund'])->toBe('overpaid')
            ->and($lapsed->refresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);
    });

    it('refunds a lapsed share in full when the bill was already settled', function () {
        $lapsed = startShare($this->table, ['amount' => 1200, 'tip' => 100]);
        $this->travel(20)->minutes();
        $whole = startShare($this->table, ['whole' => 1]);
        app(Payments::class)->confirm((string) $whole->payments()->sole()->reference);

        $payment = $lapsed->payments()->sole();
        landed($payment);

        expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
            ->and($payment->refunded)->toBe(1300)
            ->and($lapsed->refresh()->payment_status)->toBe(PaymentStatus::Refunded)
            ->and($this->order->refresh()->tip)->toBe(0)
            ->and((int) DB::table('table_bill_payment_orders')->sum('amount'))->toBe(2000);

        $this->get(route('table.pay.show', [$this->table->qr_token, $lapsed]))->assertRedirect(route('table.bill', $this->table->qr_token));
        expect($lapsed->payments()->count())->toBe(1);
    });
});

it('allows one row per gateway reference', function () {
    $order = safetyOrder();
    Payment::factory()->forOrder($order)->create(['gateway' => 'stripe', 'reference' => 'pi_same']);

    expect(fn () => Payment::factory()->forOrder($order)->create(['gateway' => 'stripe', 'reference' => 'pi_same']))
        ->toThrow(UniqueConstraintViolationException::class);

    Payment::factory()->forOrder($order)->create(['gateway' => 'fake', 'reference' => 'pi_same']);
    Payment::factory()->forOrder($order)->atVenue()->count(2)->create();

    expect(Payment::count())->toBe(4);
});
