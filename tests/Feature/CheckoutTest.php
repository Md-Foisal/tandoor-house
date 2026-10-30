<?php

use App\Actions\FindDeliveryZone;
use App\Actions\UkFormats;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\User;
use App\Services\Pricing;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    // The test client drops cookies between requests; pin the session so the basket persists.
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->seed(MenuSeeder::class);
    // Tuesday evening: open.
    $this->travelTo(CarbonImmutable::parse('2026-10-27 19:00', 'Europe/London'));
    $this->zone = DeliveryZone::factory()->covering(['E1', 'E2'])->create(['minimum_order' => 1500, 'fee' => 249]);
    DeliveryZone::factory()->covering(['E14'])->create(['name' => 'Docks', 'fee' => 349, 'minimum_order' => 2000]);
});

function fillBasket(int $quantity = 3, ?MenuItem $item = null): MenuItem
{
    $item ??= MenuItem::query()->where('is_available', true)->where('is_alcoholic', false)
        ->doesntHave('variants')->doesntHave('modifierGroups')->where('price', '>=', 500)->firstOrFail();
    test()->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => $quantity])->assertSessionHasNoErrors();

    return $item;
}

function details(array $overrides = []): array
{
    return [
        'channel' => 'delivery',
        'name' => 'Asha Rahman',
        'email' => 'asha@example.com',
        'phone' => '07700 900123',
        'line1' => '5 Fournier Street',
        'city' => 'London',
        'postcode' => 'e1  6qr',
        'payment_method' => 'card',
        'tip' => 200,
        ...$overrides,
    ];
}

it('places a guest delivery order', function () {
    fillBasket();

    $response = $this->post(route('checkout.store'), details());

    $order = Order::with(['lines', 'events'])->firstOrFail();
    $response->assertRedirect(route('orders.pay', $order->number));

    expect($order->number)->toMatch('/^TH-[A-Z2-9]{5}$/')
        ->and($order->channel)->toBe(OrderChannel::Delivery)
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($order->user_id)->toBeNull()
        ->and($order->postcode)->toBe('E1 6QR')
        ->and($order->contact['phone'])->toBe('+447700900123')
        ->and($order->contact['marketing_opt_in'])->toBeFalse()
        ->and($order->delivery_zone_id)->toBe($this->zone->id)
        ->and($order->delivery_fee)->toBe(249)
        ->and($order->tip)->toBe(200)
        ->and($order->total)->toBe($order->subtotal - $order->discount + 249 + 200)
        ->and($order->lines)->toHaveCount(1)
        ->and($order->lines[0]->quantity)->toBe(3)
        ->and($order->lines[0]->unit_price * 3)->toBe($order->subtotal)
        ->and($order->events)->toHaveCount(1)
        ->and($order->events[0]->from)->toBeNull()
        ->and($order->events[0]->to)->toBe(OrderStatus::Placed);

    expect(Cart::query()->firstOrFail()->lines)->toBe([]);
    expect(session('guest_orders'))->toBe([$order->number]);
});

it('rejects a postcode outside the zones', function () {
    fillBasket();

    $this->post(route('checkout.store'), details(['postcode' => 'SW1A 1AA']))
        ->assertSessionHasErrors('postcode');

    expect(Order::count())->toBe(0);
});

it('does not match E14 with the E1 prefix', function () {
    DeliveryZone::query()->where('id', '!=', $this->zone->id)->delete();
    $finder = app(FindDeliveryZone::class);

    expect($finder->handle('E14 9XY'))->toBeNull()
        ->and($finder->handle('E1W 3AB')?->id)->toBe($this->zone->id)
        ->and($finder->handle('E1 6QR')?->id)->toBe($this->zone->id);
});

it('checks a postcode over json', function () {
    fillBasket(1);

    $this->postJson(route('checkout.postcode'), ['postcode' => 'e2 7dp'])
        ->assertOk()
        ->assertJsonPath('covered', true)
        ->assertJsonPath('zone.fee', 249)
        ->assertJsonPath('postcode', 'E2 7DP');

    $this->postJson(route('checkout.postcode'), ['postcode' => 'M1 1AE'])
        ->assertOk()
        ->assertJsonPath('covered', false);

    $this->postJson(route('checkout.postcode'), ['postcode' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('postcode');
});

it('rejects a delivery below the zone minimum', function () {
    $cheap = MenuItem::query()->where('is_available', true)->where('is_alcoholic', false)
        ->doesntHave('variants')->doesntHave('modifierGroups')->orderBy('price')->firstOrFail();
    fillBasket(1, $cheap);
    $this->zone->update(['minimum_order' => $cheap->price + 1000]);

    $this->post(route('checkout.store'), details())->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

it('rejects a pickup slot outside opening hours', function () {
    fillBasket();

    $this->post(route('checkout.store'), details([
        'channel' => 'pickup',
        'payment_method' => 'pay_at_venue',
        'pickup_at' => '2026-10-27T03:00:00+00:00',
    ]))->assertSessionHasErrors('pickup_at');
});

it('accepts a valid pickup slot', function () {
    fillBasket();
    $slot = CarbonImmutable::parse('2026-10-27 20:00', 'Europe/London');

    $this->post(route('checkout.store'), details([
        'channel' => 'pickup',
        'payment_method' => 'pay_at_venue',
        'pickup_at' => $slot->toIso8601String(),
    ]))->assertSessionHasNoErrors();

    $order = Order::firstOrFail();
    expect($order->pickup_at->equalTo($slot))->toBeTrue()
        ->and($order->delivery_fee)->toBe(0)
        ->and($order->address)->toBeNull();
});

it('requires age confirmation for alcohol', function () {
    $beer = MenuItem::query()->where('is_alcoholic', true)->where('is_available', true)
        ->doesntHave('variants')->doesntHave('modifierGroups')->first();
    $beer ??= MenuItem::query()->where('is_alcoholic', true)->where('is_available', true)->firstOrFail();
    fillBasket(3);
    $this->post(route('basket.lines.store'), ['menu_item_id' => $beer->id, 'item_variant_id' => $beer->variants()->value('id'), 'quantity' => 1])->assertSessionHasNoErrors();

    $this->post(route('checkout.store'), details())->assertSessionHasErrors('age_confirmed');

    $this->post(route('checkout.store'), details(['age_confirmed' => true]))->assertSessionHasNoErrors();
    expect(Order::firstOrFail()->age_confirmed)->toBeTrue();
});

it('blocks an unavailable item', function () {
    $item = fillBasket();
    $item->update(['is_available' => false]);

    $this->post(route('checkout.store'), details())->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0);
});

it('blocks an empty basket on store and redirects on show', function () {
    $this->post(route('checkout.store'), details())->assertSessionHasErrors('cart');
    $this->get(route('checkout.show'))->assertRedirect(route('basket'));
});

it('shows the checkout page', function () {
    fillBasket();

    $this->get(route('checkout.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('checkout')
            ->where('channels', ['delivery', 'pickup'])
            ->has('summary.lines', 1)
            ->has('pickup.slots'));
});

it('offers dine-in only with a table', function () {
    fillBasket();
    $table = DiningTable::factory()->create();
    Cart::query()->firstOrFail()->update(['channel' => 'dine_in', 'dining_table_id' => $table->id]);

    $this->get(route('checkout.show'))
        ->assertInertia(fn ($page) => $page->where('channels', ['delivery', 'pickup', 'dine_in'])->where('channel', 'dine_in'));

    $this->post(route('checkout.store'), details(['channel' => 'dine_in', 'payment_method' => 'pay_at_venue', 'remove_service_charge' => true]))
        ->assertSessionHasNoErrors();

    $order = Order::firstOrFail();
    expect($order->dining_table_id)->toBe($table->id)
        ->and($order->service_charge)->toBe(0);
});

it('applies and removes a promo code', function () {
    fillBasket();
    PromoCode::factory()->create(['code' => 'SAVE10', 'percent_off' => 1000]);

    $this->post(route('checkout.promo'), ['code' => 'save10'])->assertSessionHasNoErrors();
    expect(Cart::query()->firstOrFail()->promo_code)->toBe('SAVE10');

    $this->post(route('checkout.promo'), ['code' => ''])->assertSessionHasNoErrors();
    expect(Cart::query()->firstOrFail()->promo_code)->toBeNull();

    $this->post(route('checkout.promo'), ['code' => 'SAVE10'])->assertSessionHasNoErrors();
    $this->post(route('checkout.promo'), [])->assertSessionHasNoErrors();
    expect(Cart::query()->firstOrFail()->promo_code)->toBeNull();
});

it('rejects unknown, expired, used-up and under-minimum promo codes', function () {
    fillBasket();
    PromoCode::factory()->expired()->create(['code' => 'OLD']);
    PromoCode::factory()->exhausted()->create(['code' => 'GONE']);
    PromoCode::factory()->amountOff(500, 999999)->create(['code' => 'BIG']);

    foreach (['NOPE', 'OLD', 'GONE', 'BIG'] as $code) {
        $this->post(route('checkout.promo'), ['code' => $code])->assertSessionHasErrors('code');
    }

    expect(Cart::query()->firstOrFail()->promo_code)->toBeNull();
});

it('counts a promo use when the order is placed', function () {
    fillBasket();
    $promo = PromoCode::factory()->create(['code' => 'SAVE10', 'percent_off' => 1000]);
    $this->post(route('checkout.promo'), ['code' => 'SAVE10']);

    $this->post(route('checkout.store'), details())->assertSessionHasNoErrors();

    $order = Order::firstOrFail();
    expect($order->discount)->toBeGreaterThan(0)
        ->and($order->promo_code_id)->toBe($promo->id)
        ->and($promo->fresh()->uses)->toBe(1);
});

it('links a signed-in order to the customer and accepts a saved address', function () {
    $user = User::factory()->create();
    $address = Address::factory()->inPostcode('E1')->create(['user_id' => $user->id]);
    $other = Address::factory()->inPostcode('E1')->create();
    $this->actingAs($user);
    fillBasket();

    $this->post(route('checkout.store'), [
        'channel' => 'delivery',
        'address_id' => $other->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '07700 900123',
        'payment_method' => 'card',
    ])->assertSessionHasErrors('line1');

    $this->post(route('checkout.store'), [
        'channel' => 'delivery',
        'address_id' => $address->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => '07700 900123',
        'payment_method' => 'card',
    ])->assertSessionHasNoErrors();

    $order = Order::firstOrFail();
    expect($order->user_id)->toBe($user->id)
        ->and($order->address['line1'])->toBe($address->line1);
});

it('rejects cash for pickup and a bad mobile number', function () {
    fillBasket();

    $this->post(route('checkout.store'), details(['channel' => 'pickup', 'payment_method' => 'cash']))
        ->assertSessionHasErrors('payment_method');
    $this->post(route('checkout.store'), details(['phone' => '020 7946 0321']))
        ->assertSessionHasErrors('phone');
});

it('normalises UK postcodes and mobiles', function () {
    expect(UkFormats::postcode(' sw1a1aa '))->toBe('SW1A 1AA')
        ->and(UkFormats::postcode('12345'))->toBeNull()
        ->and(UkFormats::mobile('+44 7700 900123'))->toBe('+447700900123')
        ->and(UkFormats::mobile('07700-900123'))->toBe('+447700900123')
        ->and(UkFormats::mobile('01234 567890'))->toBeNull();
});

it('keeps total and VAT consistent with a promo and the service charge removed', function () {
    $item = fillBasket(3);
    $table = DiningTable::factory()->create();
    Cart::query()->firstOrFail()->update(['channel' => 'dine_in', 'dining_table_id' => $table->id]);
    PromoCode::factory()->create(['code' => 'SAVE10', 'percent_off' => 1000]);
    $this->post(route('checkout.promo'), ['code' => 'SAVE10']);

    $this->post(route('checkout.store'), details(['channel' => 'dine_in', 'payment_method' => 'pay_at_venue', 'remove_service_charge' => true, 'tip' => 0]))
        ->assertSessionHasNoErrors();

    $gross = $item->price * 3;
    $discount = intdiv($gross * 1000, 10000);
    $expected = app(Pricing::class)->vat([['total' => $gross, 'vat_rate' => $item->vat_rate]], $gross, $discount);
    $order = Order::firstOrFail();

    expect($order->service_charge)->toBe(0)
        ->and($order->discount)->toBe($discount)
        ->and($order->total)->toBe($gross - $discount)
        ->and($order->vat_total)->toBe($expected);
});

it('refuses a promo code that ran out between basket and order', function () {
    fillBasket(3);
    PromoCode::factory()->create(['code' => 'LAST1', 'percent_off' => 1000, 'max_uses' => 1, 'uses' => 0]);
    $this->post(route('checkout.promo'), ['code' => 'LAST1'])->assertSessionHasNoErrors();
    $raced = false;
    DB::beforeExecuting(function (string $query) use (&$raced) {
        if (! $raced && str_starts_with($query, 'update "promo_codes"')) {
            $raced = true;
            DB::table('promo_codes')->update(['uses' => 1]);
        }
    });

    $this->post(route('checkout.store'), details())->assertSessionHasErrors('code');

    expect(Order::count())->toBe(0);
});
