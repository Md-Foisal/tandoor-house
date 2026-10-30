<?php

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Jobs\AutoAcceptOrder;
use App\Models\ActivityLog;
use App\Models\DeliveryZone;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderStateMachine;
use App\Services\PrepTimeEstimator;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Cache::flush();
    // A Wednesday lunchtime, inside opening hours.
    $this->travelTo(now('Europe/London')->next('Wednesday')->setTime(13, 0));
});

function runAutoAccept(Order $order): void
{
    AutoAcceptOrder::dispatchSync($order->id);
}

function newOrder(array $state = [], string $channel = 'pickup'): Order
{
    $item = MenuItem::factory()->create(['is_alcoholic' => false, 'prep_minutes' => 12]);
    $order = Order::factory()->{$channel}()->create(['status' => OrderStatus::Placed, 'placed_at' => now(), ...$state]);
    OrderLine::factory()->for($order)->forItem($item)->create();

    return $order->fresh();
}

function automationLog(string $event): ?ActivityLog
{
    return ActivityLog::query()->where('event', $event)->latest('id')->first();
}

it('queues the accept job when an order is placed', function () {
    Queue::fake();
    $order = newOrder();

    OrderPlaced::dispatch($order);

    Queue::assertPushed(AutoAcceptOrder::class, fn ($job) => $job->orderId === $order->id);
});

it('accepts an order that meets every rule, with no user', function () {
    $order = newOrder();

    runAutoAccept($order);

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Accepted)
        ->and($order->events()->latest('id')->first()->user_id)->toBeNull()
        ->and(automationLog('automation.order.auto_accepted'))->not->toBeNull();
});

it('accepts a delivery order inside an active zone', function () {
    DeliveryZone::factory()->create(['postcode_prefixes' => ['E2']]);
    $order = newOrder([], 'delivery');
    $order->update(['delivery_zone_id' => DeliveryZone::first()->id]);

    runAutoAccept($order);

    expect($order->refresh()->status)->toBe(OrderStatus::Accepted);
});

it('does nothing when the switch is off', function () {
    Setting::updateOrCreate(['key' => 'automations'], ['value' => ['auto_accept' => false]]);
    app(Settings::class)->flush();
    $order = newOrder();

    runAutoAccept($order);

    expect($order->refresh()->status)->toBe(OrderStatus::Placed)
        ->and(ActivityLog::query()->where('event', 'like', 'automation.order.%')->count())->toBe(0);
});

it('leaves an order for staff and says why', function (string $reason, Closure $arrange) {
    $order = $arrange();

    runAutoAccept($order);

    expect($order->refresh()->status)->toBe(OrderStatus::Placed)
        ->and(automationLog('automation.order.left_for_staff')->properties['reasons'])->toContain($reason);
})->with([
    'closed' => ['closed', function () {
        test()->travelTo(now('Europe/London')->setTime(3, 0));

        return newOrder();
    }],
    'busy' => ['busy', function () {
        app(Settings::class)->set('orders.auto_accept_max_open', 1);
        $busy = Order::factory()->create(['status' => OrderStatus::Accepted]);
        OrderLine::factory()->for($busy)->create(['status' => LineStatus::Cooking]);

        return newOrder();
    }],
    'outside zone' => ['outside_zone', function () {
        DeliveryZone::factory()->create(['postcode_prefixes' => ['E2']]);
        $order = newOrder([], 'delivery');
        $order->update(['postcode' => 'SW1A 1AA']);

        return $order;
    }],
    'age check' => ['age_check', function () {
        $order = newOrder();
        $order->lines()->first()->menuItem->update(['is_alcoholic' => true]);

        return $order;
    }],
    'unpaid card' => ['unpaid', fn () => newOrder(['payment_status' => PaymentStatus::Pending])],
    'large order' => ['large_order', function () {
        $order = newOrder();
        $order->update(['total' => 20000]);

        return $order;
    }],
]);

it('accepts a pay at venue order without payment', function () {
    $order = newOrder(['payment_method' => PaymentMethod::PayAtVenue, 'payment_status' => PaymentStatus::Pending]);

    runAutoAccept($order);

    expect($order->refresh()->status)->toBe(OrderStatus::Accepted);
});

it('is safe to run twice', function () {
    $order = newOrder();

    runAutoAccept($order);
    runAutoAccept($order);

    expect(ActivityLog::query()->where('event', 'automation.order.auto_accepted')->count())->toBe(1);
});

it('does not touch an order a person already rejected', function () {
    $order = newOrder();
    app(OrderStateMachine::class)->transition($order, OrderStatus::Rejected, User::factory()->create(), 'Too busy');

    runAutoAccept($order);

    expect($order->refresh()->status)->toBe(OrderStatus::Rejected);
});

it('routes every line to the station of its menu item at placement', function () {
    $order = newOrder();

    expect($order->lines->every(fn ($line) => $line->station === $line->menuItem->station))->toBeTrue();
});

it('estimates from the median of recent bumps and the menu figure otherwise', function () {
    $item = MenuItem::factory()->create(['prep_minutes' => 9]);
    $order = Order::factory()->create(['status' => OrderStatus::Accepted]);
    OrderLine::factory()->for($order)->forItem($item)->create();
    $estimator = app(PrepTimeEstimator::class);

    expect($estimator->minutes($order->fresh()))->toBe(9);

    foreach ([14, 16, 30] as $minutes) {
        OrderLine::factory()->for(Order::factory()->create())->forItem($item)->create([
            'status' => LineStatus::Ready,
            'started_at' => now()->subDays(2),
            'bumped_at' => now()->subDays(2)->addMinutes($minutes),
        ]);
    }
    Cache::flush();

    expect($estimator->minutes($order->fresh()))->toBe(16);
});

it('adds a station queue and the zone eta', function () {
    $item = MenuItem::factory()->create(['prep_minutes' => 10]);
    $zone = DeliveryZone::factory()->create(['eta_minutes' => 25]);
    $order = Order::factory()->delivery()->create(['status' => OrderStatus::Accepted, 'delivery_zone_id' => $zone->id]);
    OrderLine::factory()->for($order)->forItem($item)->create(['station' => 'tandoor']);

    $other = Order::factory()->create(['status' => OrderStatus::Cooking]);
    OrderLine::factory()->count(4)->for($other)->create(['station' => 'tandoor', 'status' => LineStatus::Cooking]);

    expect(app(PrepTimeEstimator::class)->minutes($order->fresh()))->toBe(10 + 2 + 25);
});

it('uses few queries however many lines there are', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Accepted]);
    OrderLine::factory()->count(8)->for($order)->create();

    DB::enableQueryLog();
    app(PrepTimeEstimator::class)->minutes($order->fresh());

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(6);
});

it('sets the estimate on accept and refreshes it when a line is bumped', function () {
    $order = newOrder();
    $order->update(['estimated_ready_at' => null]);
    runAutoAccept($order);

    expect($order->refresh()->estimated_ready_at)->not->toBeNull();

    $fast = MenuItem::factory()->create(['prep_minutes' => 4]);
    $slow = MenuItem::factory()->create(['prep_minutes' => 20]);
    $second = Order::factory()->create(['status' => OrderStatus::Accepted, 'estimated_ready_at' => null]);
    $quick = OrderLine::factory()->for($second)->forItem($fast)->create();
    OrderLine::factory()->for($second)->forItem($slow)->create();
    app(PrepTimeEstimator::class)->refresh($second->fresh());
    $first = $second->fresh()->estimated_ready_at;

    app(OrderStateMachine::class)->bump($quick->fresh());
    $slowOnly = $second->fresh()->estimated_ready_at;

    expect($first->equalTo(now()->addMinutes(20)))->toBeTrue()
        ->and($slowOnly)->not->toBeNull();
});

it('cancels unpaid card orders after thirty minutes', function () {
    $stale = newOrder(['payment_status' => PaymentStatus::Pending, 'placed_at' => now()->subMinutes(31)]);
    $fresh = newOrder(['payment_status' => PaymentStatus::Pending, 'placed_at' => now()->subMinutes(10)]);
    $paid = newOrder(['payment_status' => PaymentStatus::Paid, 'placed_at' => now()->subHour()]);
    $venue = newOrder(['payment_method' => PaymentMethod::PayAtVenue, 'payment_status' => PaymentStatus::Pending, 'placed_at' => now()->subHour()]);

    $this->artisan('orders:expire-unpaid')->assertSuccessful();
    $this->artisan('orders:expire-unpaid')->assertSuccessful();

    expect($stale->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($stale->rejection_reason)->toBe('Payment not completed')
        ->and($fresh->refresh()->status)->toBe(OrderStatus::Placed)
        ->and($paid->refresh()->status)->toBe(OrderStatus::Placed)
        ->and($venue->refresh()->status)->toBe(OrderStatus::Placed)
        ->and(ActivityLog::query()->where('event', 'automation.order.expired_unpaid')->count())->toBe(1);
});

it('estimates through the real event when staff accept an order', function () {
    $order = newOrder();
    $order->update(['estimated_ready_at' => null]);

    app(OrderStateMachine::class)->transition($order, OrderStatus::Accepted, User::factory()->create());

    expect($order->fresh()->estimated_ready_at)->not->toBeNull();
});
