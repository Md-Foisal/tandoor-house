<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(MenuSeeder::class);
    $this->withCookie(config('session.cookie'), Str::random(40));
});

function trackedOrder(array $state = [], string $as = 'live'): Order
{
    return Order::factory()->withLines(2)->{$as}(OrderStatus::Cooking)->create($state);
}

it('lets a guest follow the order they placed', function () {
    $order = trackedOrder();

    $this->withSession(['guest_orders' => [$order->number]])
        ->get("/orders/{$order->number}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/show')
            ->where('denied', false)
            ->where('order.status', 'cooking')
            ->where('order.active', true)
            ->where('order.current', 2)
            ->loadDeferredProps(fn (Assert $deferred) => $deferred
                ->has('details.lines', 2)
                ->has('details.totals.total')
                ->where('details.payment.method', 'card')));
});

it('shows another guest a friendly page with a 403', function () {
    $order = trackedOrder();

    $this->get("/orders/{$order->number}")
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('orders/show')
            ->where('denied', true)
            ->where('number', $order->number)
            ->missing('order'));
});

it('lets the customer who placed it view it', function () {
    $user = User::factory()->create();
    $order = trackedOrder(['user_id' => $user->id]);

    $this->actingAs($user)->get("/orders/{$order->number}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.reorder', true));
});

it('refuses a different customer', function () {
    $order = trackedOrder(['user_id' => User::factory()->create()->id]);

    $this->actingAs(User::factory()->create())->get("/orders/{$order->number}")
        ->assertForbidden();
});

it('lets staff view any order', function () {
    $order = trackedOrder();

    $this->actingAs(User::factory()->role(Role::Cashier)->create())
        ->get("/orders/{$order->number}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.reorder', false));
});

it('gives each channel its own steps', function (OrderChannel $channel, array $expected) {
    $order = Order::factory()->withLines(1)->{$channel === OrderChannel::DineIn ? 'dineIn' : $channel->value}()->live(OrderStatus::Ready)->create();

    $this->withSession(['guest_orders' => [$order->number]])
        ->get("/orders/{$order->number}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.steps', $expected)
            ->where('order.current', 3));
})->with([
    'delivery' => [OrderChannel::Delivery, ['placed', 'accepted', 'cooking', 'ready', 'out_for_delivery', 'delivered']],
    'pickup' => [OrderChannel::Pickup, ['placed', 'accepted', 'cooking', 'ready', 'collected']],
    'dine in' => [OrderChannel::DineIn, ['placed', 'accepted', 'cooking', 'ready', 'served']],
]);

it('names the rider by first name once the order is out', function () {
    $rider = User::factory()->role(Role::Rider)->create(['name' => 'Imran Hussain']);
    $order = Order::factory()->withLines(1)->delivery()->live(OrderStatus::OutForDelivery)->create(['rider_id' => $rider->id]);

    $this->withSession(['guest_orders' => [$order->number]])
        ->get("/orders/{$order->number}")
        ->assertInertia(fn (Assert $page) => $page->where('order.rider_name', 'Imran'));
});

it('marks a completed order as done and offers a review to its owner', function () {
    $user = User::factory()->create();
    $order = Order::factory()->withLines(1)->forCustomer($user)->completed()->create();

    $this->actingAs($user)->get("/orders/{$order->number}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.active', false)
            ->where('order.terminal', false)
            ->where('order.current', 5)
            ->where('can.review', true)
            ->where('can.reorder', true));
});

it('shows the reason and a refund note for a rejected order', function () {
    $order = Order::factory()->withLines(1)->rejected()->create(['payment_status' => PaymentStatus::Paid]);

    $this->withSession(['guest_orders' => [$order->number]])
        ->get("/orders/{$order->number}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.terminal', true)
            ->where('order.active', false)
            ->where('order.current', null)
            ->where('order.rejection_reason', 'The kitchen cannot take any more orders tonight.')
            ->where('order.refund', 'pending'));
});

it('treats a cancelled order as terminal with no refund when nothing was paid', function () {
    $order = Order::factory()->withLines(1)->cancelled()->create(['payment_status' => PaymentStatus::Pending]);

    $this->withSession(['guest_orders' => [$order->number]])
        ->get("/orders/{$order->number}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.status', 'cancelled')
            ->where('order.terminal', true)
            ->where('order.refund', null));
});

it('falls back to placed time plus the slowest dish when there is no estimate', function () {
    $order = Order::factory()->withLines(2)->live(OrderStatus::Accepted)->create(['estimated_ready_at' => null]);
    $prep = $order->lines()->with('menuItem')->get()->max(fn ($line) => $line->menuItem->prep_minutes);

    $this->withSession(['guest_orders' => [$order->number]])
        ->get("/orders/{$order->number}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.estimated_label', $order->placed_at->addMinutes($prep)->timezone('Europe/London')->format('H:i')));
});
