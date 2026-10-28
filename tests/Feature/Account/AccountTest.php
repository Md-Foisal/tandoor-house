<?php

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Address;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\PromoCode;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\MenuSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(MenuSeeder::class));

it('sends guests to sign in', function () {
    $this->get('/account')->assertRedirect('/login');
});

it('renders an empty overview for a new customer', function () {
    $customer = User::factory()->create(['name' => 'Priya Nair', 'role' => Role::Customer])->fresh();

    $this->actingAs($customer)->get('/account')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('account/show')
            ->where('firstName', 'Priya')
            ->where('isStaff', false)
            ->where('loyalty.points', 0)
            ->where('loyalty.progress', 0)
            ->where('loyalty.transactions', [])
            ->where('activeOrders', [])
            ->where('recentOrders', [])
            ->where('promos', [])
            ->where('reservations', [])
            ->where('address', null));
});

it('fills every block for a customer with data', function () {
    $customer = User::factory()->create(['name' => 'Priya Nair', 'role' => Role::Customer, 'loyalty_points' => 40]);
    $other = User::factory()->create(['role' => Role::Customer]);

    $live = Order::factory()->forCustomer($customer)->withLines(1)->live()->create();
    Order::factory()->forCustomer($other)->withLines(1)->live()->create();
    Order::factory()->forCustomer($customer)->withLines(1)->cancelled()->create();
    foreach (range(1, 4) as $day) {
        Order::factory()->forCustomer($customer)->withLines(2)->completed()->create(['placed_at' => now()->subDays($day)]);
    }
    foreach (range(1, 6) as $n) {
        LoyaltyTransaction::create(['user_id' => $customer->id, 'points' => $n, 'reason' => "Order {$n}"]);
    }

    PromoCode::factory()->create(['code' => 'WELCOME10', 'minimum_order' => 1500, 'is_public' => true]);
    PromoCode::factory()->create(['code' => 'PRIVATE20']);
    PromoCode::factory()->expired()->create();
    PromoCode::factory()->inactive()->create();
    PromoCode::factory()->exhausted()->create();

    $booked = Reservation::factory()->create(['user_id' => $customer->id]);
    Reservation::factory()->pendingDeposit()->create(['user_id' => $customer->id, 'starts_at' => now()->addDays(3)]);
    Reservation::factory()->create(['user_id' => $customer->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addHours(2)]);
    Reservation::factory()->status(ReservationStatus::Cancelled)->create(['user_id' => $customer->id]);
    Address::factory()->for($customer)->create(['label' => 'Work']);
    Address::factory()->for($customer)->default()->create(['label' => 'Home']);

    $this->actingAs($customer)->get('/account')->assertInertia(fn (Assert $page) => $page
        ->has('loyalty.transactions', 5)
        ->where('loyalty.transactions.0.reason', 'Order 6')
        ->has('activeOrders', 1)
        ->where('activeOrders.0.number', $live->number)
        ->has('recentOrders', 3)
        ->has('recentOrders.0.lines', 2)
        ->has('promos', 1)
        ->where('promos.0.code', 'WELCOME10')
        ->where('promos.0.minimum_order', 1500)
        ->has('reservations', 2)
        ->where('reservations.0.reference', $booked->reference)
        ->where('address.label', 'Home'));
});

it('works out progress to the next reward from the settings', function (int $points, int $reward, int $toNext, int $progress, int $ready) {
    app(Settings::class)->set('loyalty.reward_points', $reward);
    $customer = User::factory()->create(['role' => Role::Customer, 'loyalty_points' => $points]);

    $this->actingAs($customer)->get('/account')->assertInertia(fn (Assert $page) => $page
        ->where('loyalty.points', $points)
        ->where('loyalty.reward_points', $reward)
        ->where('loyalty.reward_value', 1000)
        ->where('loyalty.points_to_next', $toNext)
        ->where('loyalty.progress', $progress)
        ->where('loyalty.rewards_ready', $ready));
})->with([
    'part way' => [60, 150, 90, 40, 0],
    'exactly one reward' => [150, 150, 150, 0, 1],
    'one and a bit' => [200, 150, 100, 33, 1],
    'custom threshold' => [30, 100, 70, 30, 0],
]);

it('shows staff a note and keeps the overview', function () {
    $waiter = User::factory()->create(['role' => Role::Waiter])->fresh();

    $this->actingAs($waiter)->get('/account')->assertInertia(fn (Assert $page) => $page
        ->where('isStaff', true));
});

it('does not show the staff note to customers', function () {
    $customer = User::factory()->create(['role' => Role::Customer])->fresh();

    $this->actingAs($customer)->get('/account')->assertInertia(fn (Assert $page) => $page
        ->where('isStaff', false));
});
