<?php

use App\Broadcasting\Channels;
use App\Enums\Role;
use App\Enums\StaffArea;
use App\Models\Order;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // The null broadcaster lets everyone in, so check auth against Reverb's
    // signer with throwaway keys and register the channels on it.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
    ]);
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
});

function authoriseChannel(?User $user, string $channel): TestResponse
{
    $request = $user ? test()->actingAs($user) : test();

    return $request->post('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-'.$channel,
    ]);
}

it('lets each role into the staff channels its areas allow', function (Role $role) {
    $user = User::factory()->role($role)->create();

    $areas = [
        Channels::STAFF_ORDERS => StaffArea::Orders,
        Channels::STAFF_KITCHEN => StaffArea::Kitchen,
        Channels::STAFF_FLOOR => StaffArea::Floor,
        Channels::STAFF_RESERVATIONS => StaffArea::Reservations,
    ];

    foreach ($areas as $channel => $area) {
        authoriseChannel($user, $channel)->assertStatus($area->allows($role) ? 200 : 403);
    }
})->with(Role::cases());

it('keeps guests out of every staff channel', function () {
    foreach ([Channels::STAFF_ORDERS, Channels::STAFF_KITCHEN, Channels::STAFF_FLOOR, Channels::STAFF_RESERVATIONS, Channels::riderName(1)] as $channel) {
        authoriseChannel(null, $channel)->assertForbidden();
    }
});

it('lets a rider hear only their own deliveries', function () {
    $rider = User::factory()->role(Role::Rider)->create();
    $other = User::factory()->role(Role::Rider)->create();

    authoriseChannel($rider, Channels::riderName($rider->id))->assertOk();
    authoriseChannel($rider, Channels::riderName($other->id))->assertForbidden();
});

it('lets managers and owners watch any rider, and no one else', function (Role $role, bool $allowed) {
    $rider = User::factory()->role(Role::Rider)->create();

    authoriseChannel(User::factory()->role($role)->create(), Channels::riderName($rider->id))
        ->assertStatus($allowed ? 200 : 403);
})->with([
    [Role::Manager, true],
    [Role::Owner, true],
    [Role::Waiter, false],
    [Role::Cashier, false],
    [Role::Kitchen, false],
    [Role::Customer, false],
]);

it('lets only the signed-in customer who placed an order follow it', function () {
    $owner = User::factory()->create();
    $order = Order::factory()->forCustomer($owner)->create();

    authoriseChannel($owner, Channels::orderName($order))->assertOk();
    authoriseChannel(User::factory()->create(), Channels::orderName($order))->assertForbidden();
    authoriseChannel(User::factory()->role(Role::Manager)->create(), Channels::orderName($order))->assertForbidden();
    authoriseChannel(null, Channels::orderName($order))->assertForbidden();
    authoriseChannel($owner, Channels::orderName('TH-NOPE'))->assertForbidden();
});
