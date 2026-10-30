<?php

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->seed(MenuSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2026-10-27 19:00', 'Europe/London'));
    DeliveryZone::factory()->covering(['E1'])->create(['minimum_order' => 0, 'fee' => 249]);
});

function placeCardOrder(array $overrides = []): Order
{
    $item = MenuItem::query()->where('is_available', true)->where('is_alcoholic', false)
        ->doesntHave('variants')->doesntHave('modifierGroups')->where('price', '>=', 500)->firstOrFail();
    test()->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => 2])->assertSessionHasNoErrors();
    test()->post(route('checkout.store'), [
        'channel' => 'delivery',
        'name' => 'Asha Rahman',
        'email' => 'asha@example.com',
        'phone' => '07700 900123',
        'line1' => '5 Fournier Street',
        'city' => 'London',
        'postcode' => 'E1 6QR',
        'payment_method' => 'card',
        ...$overrides,
    ])->assertSessionHasNoErrors();

    return Order::query()->latest('id')->firstOrFail();
}

it('sends a card order to its pay page without telling the kitchen yet', function () {
    Event::fake([OrderPlaced::class]);

    $order = placeCardOrder();

    expect($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payment_method)->toBe(PaymentMethod::Card);
    Event::assertNotDispatched(OrderPlaced::class);
});

it('shows the pay page with the amount from the order', function () {
    $order = placeCardOrder();

    $this->get(route('orders.pay', $order->number))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('orders/pay')
            ->where('payment.amount', $order->total)
            ->where('payment.driver', 'fake')
            ->where('payment.publishableKey', null)
            ->where('order.total', $order->total));

    expect($order->payments()->count())->toBe(1);

    $this->get(route('orders.pay', $order->number))->assertOk();
    expect($order->payments()->count())->toBe(1);
});

it('takes the payment, announces the order once and is idempotent', function () {
    $order = placeCardOrder();
    Order::query()->whereKey($order->id)->update(['total' => 3000]);
    $this->get(route('orders.pay', $order->number))->assertOk();
    $payment = $order->payments()->sole();
    Event::fake([OrderPlaced::class]);

    foreach ([1, 2] as $_) {
        $this->postJson(route('orders.pay.confirm', $order->number), ['reference' => $payment->reference, 'token' => 'tok_visa'])
            ->assertOk()
            ->assertJson(['status' => 'paid', 'redirect' => route('orders.show', $order->number)]);
    }

    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Paid)
        ->and($payment->refresh()->amount)->toBe(3000);
    Event::assertDispatchedTimes(OrderPlaced::class, 1);

    $this->get(route('orders.pay', $order->number))->assertRedirect(route('orders.show', $order->number));
});

it('declines amounts ending in 51p and lets the customer retry', function () {
    $order = placeCardOrder();
    Order::query()->whereKey($order->id)->update(['total' => 3051]);
    $this->get(route('orders.pay', $order->number));
    $first = $order->payments()->sole();

    $this->postJson(route('orders.pay.confirm', $order->number), ['reference' => $first->reference, 'token' => 'tok_visa'])
        ->assertOk()
        ->assertJson(['status' => 'failed', 'redirect' => null]);
    expect($order->refresh()->payment_status)->toBe(PaymentStatus::Failed);

    $this->get(route('orders.pay', $order->number))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->whereNot('declined', null));
    expect($order->payments()->count())->toBe(2);

    Order::query()->whereKey($order->id)->update(['total' => 3000]);
    $second = $order->payments()->latest('id')->first();
    $second->update(['amount' => 3000]);

    $this->postJson(route('orders.pay.confirm', $order->number), ['reference' => $second->reference, 'token' => 'tok_visa'])
        ->assertJson(['status' => 'paid']);
});

it('declines the decline test card without closing the payment', function () {
    $order = placeCardOrder();
    $this->get(route('orders.pay', $order->number));
    $payment = $order->payments()->sole();

    $this->postJson(route('orders.pay.confirm', $order->number), ['reference' => $payment->reference, 'token' => 'tok_decline'])
        ->assertJson(['status' => 'failed']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('refuses other people and other orders payments', function () {
    $theirs = Order::factory()->create(['payment_method' => PaymentMethod::Card, 'payment_status' => PaymentStatus::Pending]);
    $foreign = Payment::factory()->forOrder($theirs)->pending()->create();

    $this->get(route('orders.pay', $theirs->number))->assertForbidden();
    $this->postJson(route('orders.pay.confirm', $theirs->number), ['reference' => $foreign->reference])->assertForbidden();
    $this->post(route('orders.pay.venue', $theirs->number))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('orders.pay', $theirs->number))->assertForbidden();

    $mine = Order::factory()->create(['payment_method' => PaymentMethod::Card, 'payment_status' => PaymentStatus::Pending]);
    $this->withSession(['guest_orders' => [$mine->number]])
        ->postJson(route('orders.pay.confirm', $mine->number), ['reference' => $foreign->reference])
        ->assertNotFound();
    expect($foreign->refresh()->status)->toBe(PaymentStatus::Pending);
});

it('lets a pickup customer switch to paying at the restaurant', function () {
    $order = placeCardOrder(['channel' => 'pickup']);
    Event::fake([OrderPlaced::class]);

    $this->post(route('orders.pay.venue', $order->number))->assertRedirect(route('orders.show', $order->number));

    expect($order->refresh()->payment_method)->toBe(PaymentMethod::PayAtVenue)
        ->and($order->payments()->where('gateway', 'venue')->exists())->toBeTrue();
    Event::assertDispatched(OrderPlaced::class);
});

it('does not let a delivery order pay at the restaurant', function () {
    $order = placeCardOrder();

    $this->post(route('orders.pay.venue', $order->number))->assertStatus(422);
    expect($order->refresh()->channel)->toBe(OrderChannel::Delivery)
        ->and($order->payment_method)->toBe(PaymentMethod::Card);
});
