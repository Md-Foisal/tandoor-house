<?php

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Events\TableRequestRaised;
use App\Events\TableStatusChanged;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TableBillPayment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-10-27 19:00', 'Europe/London'));
    $this->table = DiningTable::factory()->status(TableStatus::Seated)->create(['label' => '7']);
    $this->order = tableOrder($this->table, 2000, 200);
});

/** Food and drink 1800 plus 200 service, nothing paid. */
function tableOrder(DiningTable $table, int $total, int $service): Order
{
    return Order::factory()->create([
        'channel' => OrderChannel::DineIn,
        'dining_table_id' => $table->id,
        'payment_method' => PaymentMethod::PayAtVenue,
        'payment_status' => PaymentStatus::Pending,
        'subtotal' => $total - $service,
        'service_charge' => $service,
        'total' => $total,
        'placed_at' => now(),
    ]);
}

/** Starts a payment and opens its page, as the browser would. */
function startTablePayment(DiningTable $table, array $data): TableBillPayment
{
    test()->post(route('table.pay.store', $table->qr_token), $data)->assertSessionHasNoErrors();
    $payment = TableBillPayment::query()->latest('id')->firstOrFail();
    test()->get(route('table.pay.show', [$table->qr_token, $payment]))->assertOk();

    return $payment;
}

function confirmTablePayment(DiningTable $table, TableBillPayment $payment, ?string $token = null)
{
    $reference = $payment->payments()->latest('id')->firstOrFail()->reference;

    return test()->postJson(route('table.pay.confirm', [$table->qr_token, $payment]), array_filter(['reference' => $reference, 'token' => $token]));
}

it('pays the whole bill by card and closes the orders', function () {
    Event::fake([TableStatusChanged::class, TableRequestRaised::class]);
    $this->table->requests()->create(['type' => TableRequestType::Bill]);

    $payment = startTablePayment($this->table, ['whole' => 1]);

    expect($payment->amount)->toBe(2000)->and($payment->amountOwed())->toBe(2000);

    confirmTablePayment($this->table, $payment)
        ->assertOk()
        ->assertJson(['status' => 'paid', 'redirect' => route('table.bill', $this->table->qr_token)]);

    expect($this->order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->order->payment_method)->toBe(PaymentMethod::Card)
        ->and($payment->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->table->refresh()->status)->toBe(TableStatus::BillRequested)
        ->and($this->table->requests()->whereNull('handled_at')->count())->toBe(0);

    Event::assertDispatched(TableStatusChanged::class, fn ($event) => $event->tableId === $this->table->id && $event->to === TableStatus::BillRequested);
    Event::assertDispatched(TableRequestRaised::class);

    $this->get(route('table.bill', $this->table->qr_token))
        ->assertInertia(fn (Assert $page) => $page->where('orders', []));
});

it('lets two guests pay equal shares separately', function () {
    $first = startTablePayment($this->table, ['amount' => 1000]);
    confirmTablePayment($this->table, $first)->assertJson(['status' => 'paid']);

    expect($this->order->refresh()->payment_status)->toBe(PaymentStatus::Pending);

    $this->get(route('table.bill', $this->table->qr_token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.paid', 1000)
            ->where('totals.left', 1000));

    $second = startTablePayment($this->table, ['amount' => 1000]);
    confirmTablePayment($this->table, $second)->assertJson(['status' => 'paid']);

    expect($this->order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and((int) DB::table('table_bill_payment_orders')->sum('amount'))->toBe(2000);
});

it('spreads a payment over several orders, oldest first', function () {
    $this->travelTo(now()->addMinutes(5));
    $later = tableOrder($this->table, 1000, 100);

    confirmTablePayment($this->table, startTablePayment($this->table, ['amount' => 2500]))->assertJson(['status' => 'paid']);

    expect($this->order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($later->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('refuses to pay more than the table owes', function () {
    $this->post(route('table.pay.store', $this->table->qr_token), ['amount' => 2001])
        ->assertSessionHasErrors('amount');

    $this->post(route('table.pay.store', $this->table->qr_token), ['amount' => 0])
        ->assertSessionHasErrors('amount');

    expect(TableBillPayment::query()->count())->toBe(0);
});

it('holds a part of the bill while a share is being paid so shares cannot double up', function () {
    startTablePayment($this->table, ['amount' => 1200]);

    $this->post(route('table.pay.store', $this->table->qr_token), ['amount' => 1200])
        ->assertSessionHasErrors('amount');

    $this->post(route('table.pay.store', $this->table->qr_token), ['amount' => 800])
        ->assertSessionHasNoErrors();
});

it('cancels a lapsed share when a new one claims its part of the bill', function () {
    $first = startTablePayment($this->table, ['amount' => 1200]);
    $this->travelTo(now()->addMinutes(20));
    $second = startTablePayment($this->table, ['amount' => 1200]);

    expect($first->refresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($first->payments()->sole()->meta['cancelled'])->toBeTrue();

    confirmTablePayment($this->table, $first)->assertJson(['status' => 'failed', 'message' => __('payments.cancelled')]);
    confirmTablePayment($this->table, $second)->assertJson(['status' => 'paid']);

    expect((int) DB::table('table_bill_payment_orders')->sum('amount'))->toBe(1200)
        ->and(DB::table('table_bill_payment_orders')->where('table_bill_payment_id', $first->id)->exists())->toBeFalse();
});

it('does not settle twice when the same payment is confirmed again', function () {
    $payment = startTablePayment($this->table, ['amount' => 1000]);

    confirmTablePayment($this->table, $payment);
    confirmTablePayment($this->table, $payment)->assertJson(['status' => 'paid']);

    expect((int) DB::table('table_bill_payment_orders')->sum('amount'))->toBe(1000);
});

it('declines amounts ending in 51p and leaves the bill alone', function () {
    $payment = startTablePayment($this->table, ['amount' => 1051]);

    confirmTablePayment($this->table, $payment)->assertJson(['status' => 'failed']);

    expect($payment->refresh()->payment_status)->toBe(PaymentStatus::Failed)
        ->and($this->order->refresh()->payment_status)->toBe(PaymentStatus::Pending)
        ->and(DB::table('table_bill_payment_orders')->count())->toBe(0);

    $this->post(route('table.pay.store', $this->table->qr_token), ['whole' => 1])->assertSessionHasNoErrors();
});

it('starts a fresh attempt when a declined payment is opened again', function () {
    $payment = startTablePayment($this->table, ['amount' => 1051]);
    confirmTablePayment($this->table, $payment);

    $this->get(route('table.pay.show', [$this->table->qr_token, $payment]))->assertOk();

    expect($payment->payments()->count())->toBe(2)
        ->and($payment->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('records the tip on the order and charges it on top', function () {
    $payment = startTablePayment($this->table, ['whole' => 1, 'tip' => 250]);

    expect(Payment::query()->firstOrFail()->amount)->toBe(2250);

    confirmTablePayment($this->table, $payment);

    $order = $this->order->refresh();

    expect($order->tip)->toBe(250)
        ->and($order->total)->toBe(2250)
        ->and($order->payment_status)->toBe(PaymentStatus::Paid);
});

it('takes the service charge off when the guest leaves it out', function () {
    $payment = startTablePayment($this->table, ['whole' => 1, 'service' => 0]);

    expect($payment->amount)->toBe(1800)->and($payment->waived)->toBe(200);

    confirmTablePayment($this->table, $payment);

    expect($this->order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('keeps another table out of this bill', function () {
    $other = DiningTable::factory()->create();
    tableOrder($other, 500, 0);
    $payment = startTablePayment($this->table, ['amount' => 1000]);
    $reference = $payment->payments()->firstOrFail()->reference;

    $this->get(route('table.pay.show', [$other->qr_token, $payment]))->assertForbidden();
    $this->postJson(route('table.pay.confirm', [$other->qr_token, $payment]), ['reference' => $reference])->assertForbidden();

    expect($payment->refresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('pays against the lead table when tables are merged', function () {
    $joined = DiningTable::factory()->create(['merged_into_id' => $this->table->id]);
    $other = tableOrder($joined, 600, 0);

    confirmTablePayment($this->table, startTablePayment($this->table, ['whole' => 1]))->assertJson(['status' => 'paid']);

    expect($other->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($this->order->refresh()->payment_status)->toBe(PaymentStatus::Paid);
});
