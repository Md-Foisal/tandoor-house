<?php

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Shift;
use App\Models\TableRequest;
use App\Models\User;

beforeEach(fn () => $this->travelTo('2026-10-28 18:00:00'));

function todayStaff(Role $role): User
{
    return User::factory()->role($role)->create();
}

function todayOrder(array $attributes): Order
{
    return Order::factory()->create($attributes);
}

it('shows each role its own blocks', function (Role $role, string $view, array $keys) {
    $this->actingAs(todayStaff($role))->get('/staff')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/today')
            ->where('view', $view)
            ->missing('blocks')
            ->loadDeferredProps(fn ($page) => $page->has('blocks', fn ($blocks) => $blocks->hasAll($keys)->etc())));
})->with([
    'owner' => [Role::Owner, 'management', ['tiles', 'hourly', 'attention']],
    'manager' => [Role::Manager, 'management', ['tiles', 'hourly', 'attention']],
    'cashier' => [Role::Cashier, 'front', ['waiting', 'ready', 'table_requests', 'bookings']],
    'waiter' => [Role::Waiter, 'front', ['waiting', 'ready', 'table_requests', 'bookings']],
    'kitchen' => [Role::Kitchen, 'kitchen', ['in_kitchen', 'late', 'low_stock']],
    'rider' => [Role::Rider, 'rider', ['out_now', 'to_collect', 'cash_collected']],
]);

it('greets by first name with the open status and service periods', function () {
    $user = User::factory()->role(Role::Waiter)->create(['name' => 'Priya Nair']);

    $this->actingAs($user)->get('/staff')->assertInertia(fn ($page) => $page
        ->where('first_name', 'Priya')
        ->where('date', 'Wednesday 28 October')
        ->where('open.isOpen', true)
        ->where('periods', ['12:00 – 15:00', '17:30 – 22:30']));
});

it('adds up the manager numbers from a controlled evening', function () {
    $table = DiningTable::factory()->create(['label' => 'T7']);

    todayOrder(['channel' => OrderChannel::Pickup, 'status' => OrderStatus::Completed, 'total' => 2000, 'placed_at' => '2026-10-28 12:30', 'accepted_at' => '2026-10-28 12:31', 'ready_at' => '2026-10-28 12:51', 'completed_at' => '2026-10-28 13:10']);
    todayOrder(['channel' => OrderChannel::Delivery, 'status' => OrderStatus::Cooking, 'total' => 3000, 'placed_at' => '2026-10-28 17:40', 'accepted_at' => '2026-10-28 17:41']);
    $slow = todayOrder(['channel' => OrderChannel::DineIn, 'dining_table_id' => $table->id, 'status' => OrderStatus::Placed, 'total' => 1000, 'placed_at' => '2026-10-28 17:50']);
    todayOrder(['status' => OrderStatus::Rejected, 'total' => 1500, 'placed_at' => '2026-10-28 17:00']);
    todayOrder(['status' => OrderStatus::Completed, 'total' => 4000, 'placed_at' => '2026-10-21 13:00']);

    Reservation::factory()->at('2026-10-28 19:00')->forParty(4)->create();
    Reservation::factory()->at('2026-10-28 20:00')->pendingDeposit(6)->create();
    Reservation::factory()->at('2026-10-29 19:00')->create();

    Ingredient::factory()->low()->create(['name' => 'Basmati rice']);
    Ingredient::factory()->create();
    Review::factory()->unpublished()->create();
    Review::factory()->create();
    TableRequest::query()->forceCreate(['dining_table_id' => $table->id, 'type' => 'bill']);

    Attendance::create(['user_id' => todayStaff(Role::Waiter)->id, 'clocked_in_at' => now()->subHour()]);
    Attendance::create(['user_id' => todayStaff(Role::Kitchen)->id, 'clocked_in_at' => now()->subHours(2), 'clocked_out_at' => now()->subHour()]);

    $this->actingAs(todayStaff(Role::Manager))->get('/staff')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->where('blocks.tiles.sales', 6000)
            ->where('blocks.tiles.orders_by_channel', ['delivery' => 1, 'pickup' => 1, 'dine_in' => 1])
            ->where('blocks.tiles.covers_tonight', 10)
            ->where('blocks.tiles.average_prep_minutes', 20)
            ->where('blocks.tiles.late_tickets', 1)
            ->where('blocks.tiles.low_stock', 1)
            ->where('blocks.tiles.staff_on_shift', 1)
            ->where('blocks.hourly.1.today', 2000)
            ->where('blocks.hourly.6.today', 4000)
            ->where('blocks.hourly.2.hour', 13)
            ->where('blocks.hourly.2.last_week', 4000)
            ->where('blocks.hourly.8.today', null)
            ->has('blocks.attention.slow_orders', 1)
            ->where('blocks.attention.slow_orders.0.number', $slow->number)
            ->has('blocks.attention.table_requests', 1)
            ->has('blocks.attention.unpaid_deposits', 1)
            ->where('blocks.attention.low_stock.0.name', 'Basmati rice')
            ->where('blocks.attention.unpublished_reviews', 1)));
});

it('shows the front of house what it can act on', function () {
    $waiting = todayOrder(['status' => OrderStatus::Placed, 'placed_at' => now()->subMinutes(2)]);
    $ready = todayOrder(['status' => OrderStatus::Ready, 'ready_at' => now()->subMinute(), 'placed_at' => now()->subMinutes(20)]);
    todayOrder(['channel' => OrderChannel::Delivery, 'status' => OrderStatus::Ready, 'placed_at' => now()->subMinutes(20)]);
    Reservation::factory()->at('2026-10-28 19:30')->create(['name' => 'Soon']);
    Reservation::factory()->at('2026-10-28 21:00')->create(['name' => 'Later']);

    $this->actingAs(todayStaff(Role::Cashier))->get('/staff')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->has('blocks.waiting', 1)
            ->where('blocks.waiting.0.number', $waiting->number)
            ->has('blocks.ready', 1)
            ->where('blocks.ready.0.number', $ready->number)
            ->has('blocks.bookings', 1)
            ->where('blocks.bookings.0.name', 'Soon')));
});

it('shows the kitchen its tickets, late ones and low stock', function () {
    todayOrder(['status' => OrderStatus::Cooking, 'placed_at' => now()->subMinutes(30), 'accepted_at' => now()->subMinutes(25)]);
    todayOrder(['status' => OrderStatus::Accepted, 'placed_at' => now()->subMinutes(5), 'accepted_at' => now()->subMinutes(4)]);
    Ingredient::factory()->low()->create();

    $this->actingAs(todayStaff(Role::Kitchen))->get('/staff')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->where('blocks.in_kitchen', 2)
            ->where('blocks.late', 1)
            ->where('blocks.low_stock_count', 1)));
});

it('shows a rider only their own deliveries and cash', function () {
    $rider = todayStaff(Role::Rider);
    $other = todayStaff(Role::Rider);
    $base = ['channel' => OrderChannel::Delivery, 'payment_method' => PaymentMethod::Cash, 'placed_at' => now()->subMinutes(30)];

    todayOrder([...$base, 'rider_id' => $rider->id, 'status' => OrderStatus::OutForDelivery, 'total' => 1800]);
    todayOrder([...$base, 'rider_id' => $rider->id, 'status' => OrderStatus::Ready, 'ready_at' => now()->subMinutes(2)]);
    todayOrder([...$base, 'rider_id' => $rider->id, 'status' => OrderStatus::Delivered, 'cash_collected' => 2250]);
    todayOrder([...$base, 'rider_id' => $other->id, 'status' => OrderStatus::OutForDelivery]);

    $this->actingAs($rider)->get('/staff')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($page) => $page
            ->has('blocks.out_now', 1)
            ->where('blocks.out_now.0.cash_due', 1800)
            ->has('blocks.to_collect', 1)
            ->where('blocks.cash_collected', 2250)
            ->where('blocks.delivered_today', 1)));
});

it('shows my shift for today and whether I am clocked in', function () {
    $user = todayStaff(Role::Waiter);
    Shift::factory()->create(['user_id' => $user->id, 'role' => Role::Waiter, 'starts_at' => '2026-10-28 17:00', 'ends_at' => '2026-10-28 23:00']);
    Shift::factory()->create(['user_id' => $user->id, 'role' => Role::Waiter, 'starts_at' => '2026-10-29 17:00', 'ends_at' => '2026-10-29 23:00']);

    $this->actingAs($user)->get('/staff')->assertInertia(fn ($page) => $page
        ->where('shift.shift.role', 'waiter')
        ->where('shift.clocked_in_at', null));

    $this->actingAs($user)->post('/staff/clock-in')->assertRedirect();

    $this->actingAs($user)->get('/staff')->assertInertia(fn ($page) => $page
        ->where('shift.clocked_in_at', now()->toIso8601String()));
});

it('clocks in against today shift and logs it', function () {
    $user = todayStaff(Role::Cashier);
    $shift = Shift::factory()->create(['user_id' => $user->id, 'role' => Role::Cashier, 'starts_at' => '2026-10-28 17:00', 'ends_at' => '2026-10-28 23:00']);

    $this->actingAs($user)->post('/staff/clock-in')->assertSessionHasNoErrors();

    $attendance = Attendance::sole();
    expect($attendance->user_id)->toBe($user->id)
        ->and($attendance->shift_id)->toBe($shift->id)
        ->and($attendance->clocked_out_at)->toBeNull()
        ->and(ActivityLog::where('event', 'clock_in')->where('user_id', $user->id)->count())->toBe(1);
});

it('refuses a second clock in while one is open', function () {
    $user = todayStaff(Role::Waiter);

    $this->actingAs($user)->post('/staff/clock-in');
    $this->actingAs($user)->post('/staff/clock-in')->assertSessionHasErrors('clock');

    expect(Attendance::count())->toBe(1);
});

it('clocks out the open attendance and logs it', function () {
    $user = todayStaff(Role::Waiter);
    $this->actingAs($user)->post('/staff/clock-in');
    $this->travelTo('2026-10-28 22:00:00');

    $this->actingAs($user)->post('/staff/clock-out')->assertSessionHasNoErrors();

    expect(Attendance::sole()->clocked_out_at?->toDateTimeString())->toBe('2026-10-28 22:00:00')
        ->and(ActivityLog::where('event', 'clock_out')->count())->toBe(1);
});

it('refuses clocking out without an open attendance', function () {
    $user = todayStaff(Role::Waiter);
    Attendance::create(['user_id' => $user->id, 'clocked_in_at' => now()->subHours(3), 'clocked_out_at' => now()->subHour()]);

    $this->actingAs($user)->post('/staff/clock-out')->assertSessionHasErrors('clock');

    expect(ActivityLog::where('event', 'clock_out')->count())->toBe(0);
});

it('keeps clock actions to staff', function () {
    $this->actingAs(User::factory()->create())->post('/staff/clock-in')->assertForbidden();
    expect(Attendance::count())->toBe(0);
});

it('refuses clocking out twice and records one clock-out', function () {
    $user = todayStaff(Role::Waiter);
    $this->actingAs($user)->post('/staff/clock-in');

    $this->actingAs($user)->post('/staff/clock-out')->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/staff/clock-out')->assertSessionHasErrors('clock');

    expect(ActivityLog::where('event', 'clock_out')->count())->toBe(1);
});
