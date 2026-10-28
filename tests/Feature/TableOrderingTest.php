<?php

use App\Actions\SplitBill;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\TableRequest;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withCookie(config('session.cookie'), Str::random(40));
    // Tuesday evening: open.
    $this->travelTo(CarbonImmutable::parse('2026-10-27 19:00', 'Europe/London'));
    $this->seed(MenuSeeder::class);
    $this->table = DiningTable::factory()->create(['label' => '7', 'area' => 'Main room']);
});

function addDish(int $quantity = 2): MenuItem
{
    $item = MenuItem::query()->where('is_available', true)->doesntHave('variants')->doesntHave('modifierGroups')->firstOrFail();
    test()->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => $quantity])->assertSessionHasNoErrors();

    return $item;
}

it('sets the dine-in channel and table when the code is scanned', function () {
    $this->get(route('table.show', $this->table->qr_token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('table/show')
            ->where('table.label', '7')
            ->where('table.area', 'Main room'));

    $cart = Cart::query()->firstOrFail();
    expect($cart->channel)->toBe('dine_in')->and($cart->dining_table_id)->toBe($this->table->id);
});

it('404s an unknown token', function () {
    $this->get('/t/nope')->assertNotFound();
});

it('redirects a merged table to its lead table', function () {
    $lead = DiningTable::factory()->create();
    $this->table->update(['merged_into_id' => $lead->id]);

    $this->get(route('table.show', $this->table->qr_token))->assertRedirect(route('table.show', $lead->qr_token));
    $this->get(route('table.bill', $this->table->qr_token))->assertRedirect(route('table.bill', $lead->qr_token));
});

it('sends the basket to the kitchen as a dine-in order and clears it', function () {
    $this->get(route('table.show', $this->table->qr_token));
    $item = addDish(2);

    $this->post(route('table.order', $this->table->qr_token))
        ->assertRedirect(route('table.show', $this->table->qr_token));

    $order = Order::with('lines', 'events')->firstOrFail();
    expect($order->channel)->toBe(OrderChannel::DineIn)
        ->and($order->status)->toBe(OrderStatus::Placed)
        ->and($order->dining_table_id)->toBe($this->table->id)
        ->and($order->payment_method)->toBe(PaymentMethod::PayAtVenue)
        ->and($order->lines)->toHaveCount(1)
        ->and($order->lines[0]->menu_item_id)->toBe($item->id)
        ->and($order->lines[0]->quantity)->toBe(2)
        ->and($order->service_charge)->toBeGreaterThan(0)
        ->and($order->events)->toHaveCount(1)
        ->and(Cart::query()->firstOrFail()->lines)->toBe([]);
});

it('refuses to send an empty basket', function () {
    $this->post(route('table.order', $this->table->qr_token))->assertSessionHasErrors('basket');

    expect(Order::count())->toBe(0);
});

it('creates one open waiter call and refuses a duplicate', function () {
    $url = route('table.request', $this->table->qr_token);

    $this->post($url, ['type' => 'call_waiter'])->assertSessionHasNoErrors();
    $this->post($url, ['type' => 'call_waiter'])->assertSessionHasErrors('type');
    $this->post($url, ['type' => 'bad'])->assertSessionHasErrors('type');

    expect(TableRequest::query()->where('type', 'call_waiter')->count())->toBe(1);
});

it('creates one open bill request and allows a new one after it is handled', function () {
    $url = route('table.request', $this->table->qr_token);

    $this->post($url, ['type' => 'bill']);
    $this->post($url, ['type' => 'bill'])->assertSessionHasErrors('type');
    TableRequest::query()->update(['handled_at' => now()]);
    $this->post($url, ['type' => 'bill'])->assertSessionHasNoErrors();

    expect(TableRequest::query()->where('type', 'bill')->count())->toBe(2);
});

it('splits evenly with the exact total', function (int $total, int $people) {
    $shares = (new SplitBill)->evenly($total, $people);

    expect($shares)->toHaveCount($people)->and(array_sum($shares))->toBe($total);
    expect($shares[0])->toBeGreaterThanOrEqual($shares[$people - 1]);
})->with([[10000, 3], [9999, 7], [1, 12], [4567, 2]]);

function seedVisit(DiningTable $table): Order
{
    $items = MenuItem::query()->where('is_available', true)->doesntHave('variants')->limit(2)->get();
    $order = Order::factory()->create([
        'channel' => OrderChannel::DineIn,
        'dining_table_id' => $table->id,
        'status' => OrderStatus::Cooking,
        'payment_method' => PaymentMethod::PayAtVenue,
        'payment_status' => 'pending',
    ]);
    OrderLine::factory()->for($order)->forItem($items[0])->create(['quantity' => 2]);
    OrderLine::factory()->for($order)->forItem($items[1])->create(['quantity' => 1]);

    return $order->refresh();
}

it('shows an even split that adds up to the bill', function () {
    $order = seedVisit($this->table);

    $this->get(route('table.bill', [$this->table->qr_token, 'mode' => 'even', 'people' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('table/bill')
            ->where('totals.total', $order->total)
            ->where('split.shares', fn ($shares) => count($shares) === 7 && collect($shares)->sum() === $order->total));
});

it('shows an item split that adds up to the bill, with or without the service charge', function () {
    $order = seedVisit($this->table);
    $units = $order->lines->flatMap(fn (OrderLine $line) => collect(range(1, $line->quantity))->map(fn ($n) => $line->id.'-'.$n))->values();
    $claims = $units->mapWithKeys(fn ($key, $index) => [$key => $index % 3])->all();

    $this->get(route('table.bill', [$this->table->qr_token, 'mode' => 'items', 'guests' => 3, 'claims' => $claims]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('split.unclaimed', 0)
            ->where('split.shares', fn ($shares) => collect($shares)->sum() === $order->total));

    $this->get(route('table.bill', [$this->table->qr_token, 'service' => 0, 'mode' => 'items', 'guests' => 3, 'claims' => $claims]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('totals.total', $order->total - $order->service_charge)
            ->where('split.shares', fn ($shares) => collect($shares)->sum() === $order->total - $order->service_charge));
});

it('leaves unclaimed items out of the shares but keeps the sum exact', function () {
    $order = seedVisit($this->table);
    $first = $order->lines->first()->id.'-1';

    $this->get(route('table.bill', [$this->table->qr_token, 'mode' => 'items', 'guests' => 2, 'claims' => [$first => 0]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('split.unclaimed', fn ($left) => $left > 0)
            ->where('split.shares', fn ($shares) => $shares[1] === 0));
});

it('leaves out paid and other days orders from the bill', function () {
    seedVisit($this->table);
    Order::factory()->create(['channel' => OrderChannel::DineIn, 'dining_table_id' => $this->table->id, 'status' => OrderStatus::Completed]);

    $this->get(route('table.bill', $this->table->qr_token))
        ->assertInertia(fn (Assert $page) => $page->has('orders', 1));
});

function addBeer(): MenuItem
{
    $beer = MenuItem::query()->where('is_alcoholic', true)->where('is_available', true)->firstOrFail();
    test()->post(route('basket.lines.store'), ['menu_item_id' => $beer->id, 'item_variant_id' => $beer->variants()->value('id'), 'quantity' => 1])->assertSessionHasNoErrors();

    return $beer;
}

it('needs age confirmation to send alcohol from the table', function () {
    $this->get(route('table.show', $this->table->qr_token));
    addBeer();

    $this->post(route('table.order', $this->table->qr_token))->assertSessionHasErrors('age_confirmed');
    expect(Order::count())->toBe(0);

    $this->post(route('table.order', $this->table->qr_token), ['age_confirmed' => true])->assertSessionHasNoErrors();
    expect(Order::firstOrFail()->age_confirmed)->toBeTrue();
});

it('does not ask for age confirmation when there is no alcohol', function () {
    $this->get(route('table.show', $this->table->qr_token));
    addDish(1);

    $this->post(route('table.order', $this->table->qr_token))->assertSessionHasNoErrors();
    expect(Order::firstOrFail()->age_confirmed)->toBeFalse();
});

it('refuses table orders when the restaurant is closed', function () {
    $this->get(route('table.show', $this->table->qr_token));
    addDish(1);
    $this->travelTo(CarbonImmutable::parse('2026-10-27 04:00', 'Europe/London'));

    $this->post(route('table.order', $this->table->qr_token))->assertSessionHasErrors('basket');

    expect(Order::count())->toBe(0)->and(Cart::query()->firstOrFail()->lines)->not->toBe([]);
});

it('does not write to the basket when the table page is polled', function () {
    $this->get(route('table.show', $this->table->qr_token));
    $writes = 0;
    DB::listen(function ($query) use (&$writes) {
        if (str_starts_with($query->sql, 'update "carts"')) {
            $writes++;
        }
    });

    $this->get(route('table.show', $this->table->qr_token))->assertOk();

    expect($writes)->toBe(0);
});
