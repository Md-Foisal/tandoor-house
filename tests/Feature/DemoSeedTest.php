<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Services\Pricing;
use Database\Factories\OrderFactory;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('seeds orders whose totals add up', function () {
    $pricing = app(Pricing::class);
    $orders = Order::with(['lines', 'deliveryZone'])->get();

    expect($orders->count())->toBeGreaterThan(300);

    foreach ($orders as $order) {
        $lines = $order->lines->map(fn ($line) => ['total' => $line->unit_price * $line->quantity, 'vat_rate' => $line->vat_rate])->all();
        $subtotal = array_sum(array_column($lines, 'total'));

        expect($order->lines)->not->toBeEmpty()
            ->and($order->subtotal)->toBe($subtotal)
            ->and($order->total)->toBe($subtotal - $order->discount + $order->delivery_fee + $order->service_charge + $order->tip)
            ->and($order->vat_total)->toBe($pricing->vat($lines, $subtotal, $order->discount))
            ->and($order->delivery_fee)->toBe($order->channel === OrderChannel::Delivery ? $order->deliveryZone->fee : 0)
            ->and($order->service_charge > 0)->toBe($order->channel === OrderChannel::DineIn && $subtotal - $order->discount >= 8);
    }
});

it('keeps order lines, events and payments consistent with the order', function () {
    Order::with(['lines', 'events', 'payments'])->get()->each(function (Order $order) {
        expect($order->events->first()->to)->toBe(OrderStatus::Placed)
            ->and($order->events->last()->to)->toBe($order->status)
            ->and($order->payments->sum('amount'))->toBeIn([0, $order->total]);

        if ($order->channel === OrderChannel::Delivery && in_array($order->status, [OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed], true)) {
            expect($order->rider_id)->not->toBeNull();
        }
    });
});

it('does not trade on Mondays', function () {
    $mondays = Order::query()
        ->whereIn('status', [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled])
        ->get()
        ->filter(fn (Order $order) => $order->placed_at->setTimezone('Europe/London')->isMonday());

    expect($mondays)->toBeEmpty();
});

it('gives every menu item a recipe', function () {
    expect(MenuItem::count())->toBeGreaterThan(0)
        ->and(MenuItem::doesntHave('recipeLines')->count())->toBe(0)
        ->and(Ingredient::whereColumn('stock', '<', 'low_threshold')->count())->toBeBetween(3, 10);
});

it('has a live order in every active status right now', function () {
    $live = Order::where('placed_at', '>=', now()->subMinutes(60))->get()->groupBy(fn (Order $order) => $order->status->value);

    foreach (['placed', 'accepted', 'cooking', 'ready', 'out_for_delivery', 'served'] as $status) {
        expect($live->get($status))->not->toBeNull("no live order is {$status}");
    }

    $cooking = $live->get('cooking')->load('lines')->flatMap->lines->pluck('status.value')->unique();

    expect($cooking->all())->toContain('cooking')
        ->and(DiningTable::count())->toBe(18)
        ->and(DB::table('table_requests')->whereNull('handled_at')->pluck('type')->sort()->values()->all())->toBe(['bill', 'call_waiter']);
});

it('never double-books a table', function () {
    $reservations = Reservation::whereNotNull('dining_table_id')
        ->whereNotIn('status', [ReservationStatus::Cancelled])
        ->orderBy('starts_at')
        ->get()
        ->groupBy('dining_table_id');

    expect(Reservation::count())->toBeGreaterThan(100);

    foreach ($reservations as $forTable) {
        $previousEnd = null;

        foreach ($forTable as $reservation) {
            if ($previousEnd !== null) {
                expect($reservation->starts_at->greaterThanOrEqualTo($previousEnd))->toBeTrue();
            }

            $previousEnd = $reservation->ends_at;
        }
    }
});

it('only asks for deposits from parties of six or more', function () {
    Reservation::all()->each(function (Reservation $reservation) {
        expect($reservation->deposit)->toBe($reservation->party_size >= 6 ? $reservation->party_size * 1000 : 0);

        if ($reservation->status === ReservationStatus::PendingDeposit) {
            expect($reservation->party_size)->toBeGreaterThanOrEqual(6);
        }
    });
});

it('gives the demo customer a believable account', function () {
    $demo = User::where('email', 'customer@tandoorhouse.test')->firstOrFail();

    expect($demo->addresses)->toHaveCount(2)
        ->and($demo->addresses->firstWhere('is_default', true)->label)->toBe('Home')
        ->and(Order::where('user_id', $demo->id)->count())->toBeGreaterThanOrEqual(12)
        ->and(Review::where('user_id', $demo->id)->count())->toBe(2)
        ->and($demo->loyalty_points)->toBe((int) $demo->loyaltyTransactions()->sum('points'))
        ->and($demo->loyalty_points)->toBeGreaterThan(0);

    $cart = Cart::where('user_id', $demo->id)->firstOrFail();

    expect($cart->lines)->toHaveCount(2)
        ->and(DB::table('carts')->where('user_id', $demo->id)->value('updated_at'))->toBeLessThan(now()->subHours(2)->toDateTimeString());
});

it('keeps loyalty balances equal to their transactions', function () {
    User::query()->where('loyalty_points', '>', 0)->get()->each(function (User $user) {
        expect($user->loyalty_points)->toBe((int) $user->loyaltyTransactions()->sum('points'));
    });
});

it('covers every delivery postcode with a zone', function () {
    $prefixes = DeliveryZone::all()->flatMap->postcode_prefixes;

    Order::where('channel', OrderChannel::Delivery)->get()->each(function (Order $order) use ($prefixes) {
        expect($prefixes)->toContain(explode(' ', (string) $order->postcode)[0]);
    });
});

it('builds consistent orders from the factory', function () {
    $order = Order::factory()->delivery()->completed()->withLines(3)->create()->fresh();
    $dineIn = Order::factory()->dineIn()->live()->withLines()->create()->fresh();

    expect($order->subtotal)->toBeGreaterThan(0)
        ->and($order->total)->toBe($order->subtotal + $order->delivery_fee)
        ->and($order->events()->count())->toBe(count(OrderFactory::path(OrderChannel::Delivery)))
        ->and($dineIn->service_charge)->toBe(intdiv($dineIn->subtotal * 1250, 10000))
        ->and($dineIn->total)->toBe($dineIn->subtotal + $dineIn->service_charge);
});
