<?php

use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\Station;
use App\Http\Controllers\MenuController;
use App\Models\ActivityLog;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

function kitchenUser(Role $role = Role::Kitchen): User
{
    return User::factory()->role($role)->create();
}

function railOrder(OrderStatus $status = OrderStatus::Accepted, array $stations = [Station::Tandoor, Station::Curry]): Order
{
    $order = Order::factory()->live($status)->create();

    foreach ($stations as $station) {
        $item = MenuItem::factory()->create(['station' => $station, 'prep_minutes' => 12]);
        OrderLine::factory()->for($order)->forItem($item)->create();
    }

    return $order->refresh();
}

it('keeps everyone but kitchen, manager and owner off the display', function (?Role $role, int $status) {
    $user = $role ? kitchenUser($role) : User::factory()->create();

    $this->actingAs($user)->get('/staff/kitchen')->assertStatus($status);
})->with([
    'waiter' => [Role::Waiter, 403],
    'cashier' => [Role::Cashier, 403],
    'rider' => [Role::Rider, 403],
    'customer' => [null, 403],
    'kitchen' => [Role::Kitchen, 200],
    'manager' => [Role::Manager, 200],
    'owner' => [Role::Owner, 200],
]);

it('shows only accepted and cooking orders, oldest first', function () {
    $newer = railOrder(OrderStatus::Cooking);
    $older = railOrder(OrderStatus::Accepted);
    $older->forceFill(['accepted_at' => now()->subMinutes(30)])->save();
    railOrder(OrderStatus::Placed);
    railOrder(OrderStatus::Ready);

    $this->actingAs(kitchenUser())->get('/staff/kitchen')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/kitchen/index')
            ->has('tickets', 2)
            ->where('tickets.0.number', $older->number)
            ->where('tickets.1.number', $newer->number));
});

it('filters ticket lines by station and counts each station', function () {
    $order = railOrder(stations: [Station::Tandoor, Station::Curry]);
    railOrder(stations: [Station::Curry]);

    $this->actingAs(kitchenUser())->get('/staff/kitchen?station=tandoor')
        ->assertInertia(fn ($page) => $page
            ->where('station', 'tandoor')
            ->has('tickets', 1)
            ->where('tickets.0.number', $order->number)
            ->has('tickets.0.lines', 1)
            ->where('tickets.0.lines.0.station', 'tandoor')
            ->where('stations.0.count', 2)
            ->where('stations.1.count', 1)
            ->where('stations.2.count', 2));

    $this->actingAs(kitchenUser())->get('/staff/kitchen?station=nonsense')
        ->assertInertia(fn ($page) => $page->where('station', 'all')->has('tickets', 2));
});

it('flags allergy notes', function () {
    $order = railOrder(stations: [Station::Curry]);
    $order->lines()->first()->update(['notes' => 'Severe nut allergy']);

    $this->actingAs(kitchenUser())->get('/staff/kitchen')
        ->assertInertia(fn ($page) => $page->where('tickets.0.lines.0.allergy', true));
});

it('bumps a line, starts cooking, and readies the order when the last line goes', function () {
    $user = kitchenUser();
    $order = railOrder();
    [$first, $second] = $order->lines;

    $this->actingAs($user)->post("/staff/kitchen/lines/{$first->id}/bump")->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Cooking)
        ->and($first->refresh()->status)->toBe(LineStatus::Ready);

    $this->actingAs($user)->post("/staff/kitchen/lines/{$second->id}/bump")->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Ready);

    $this->actingAs($user)->get('/staff/kitchen')
        ->assertInertia(fn ($page) => $page->has('tickets', 0)->has('recent', 2));
});

it('bumps a whole ticket for one station', function () {
    $order = railOrder();

    $this->actingAs(kitchenUser())->post("/staff/kitchen/orders/{$order->id}/bump", ['station' => 'tandoor'])->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Cooking);

    $this->actingAs(kitchenUser())->post("/staff/kitchen/orders/{$order->id}/bump", ['station' => 'all'])->assertRedirect();
    expect($order->refresh()->status)->toBe(OrderStatus::Ready);
});

it('refuses to bump with an unknown station or a ticket off the rail', function () {
    $order = railOrder();
    $done = railOrder(OrderStatus::Ready);

    $this->actingAs(kitchenUser())->post("/staff/kitchen/orders/{$order->id}/bump", ['station' => 'grill'])->assertSessionHasErrors('station');
    $this->actingAs(kitchenUser())->post("/staff/kitchen/lines/{$done->lines->first()->id}/bump")->assertSessionHasErrors('line');
});

it('recalls a bumped line and puts a ready order back on the rail', function () {
    $user = kitchenUser();
    $order = railOrder(stations: [Station::Tandoor]);
    $line = $order->lines->first();

    $this->actingAs($user)->post("/staff/kitchen/lines/{$line->id}/bump");
    expect($order->refresh()->status)->toBe(OrderStatus::Ready);

    $this->actingAs($user)->post("/staff/kitchen/lines/{$line->id}/recall")->assertRedirect();

    expect($order->refresh()->status)->toBe(OrderStatus::Cooking)
        ->and($line->refresh()->status)->toBe(LineStatus::Cooking);
});

it('forgets bumped lines after ten minutes', function () {
    $order = railOrder(OrderStatus::Cooking);
    $order->lines->first()->update(['status' => LineStatus::Ready, 'bumped_at' => now()->subMinutes(11)]);

    $this->actingAs(kitchenUser())->get('/staff/kitchen')
        ->assertInertia(fn ($page) => $page->has('recent', 0));
});

it('lets the kitchen mark a dish sold out, logged, clearing the menu cache', function () {
    $user = kitchenUser();
    $order = railOrder(stations: [Station::Tandoor]);
    $item = $order->lines->first()->menuItem;
    Cache::put(MenuController::CACHE_KEY, 'stale');

    $this->actingAs($user)->post("/staff/kitchen/items/{$item->id}/sold-out")->assertRedirect();

    expect($item->refresh()->is_available)->toBeFalse()
        ->and(Cache::has(MenuController::CACHE_KEY))->toBeFalse();

    $log = ActivityLog::query()->where('event', 'menu.sold_out')->sole();
    expect($log->user_id)->toBe($user->id)
        ->and($log->subject_id)->toBe($item->id);
});

it('stops other roles marking dishes sold out or bumping', function (Role $role) {
    $order = railOrder(stations: [Station::Tandoor]);
    $line = $order->lines->first();
    $user = kitchenUser($role);

    $this->actingAs($user)->post("/staff/kitchen/items/{$line->menu_item_id}/sold-out")->assertForbidden();
    $this->actingAs($user)->post("/staff/kitchen/lines/{$line->id}/bump")->assertForbidden();
    expect($line->menuItem->refresh()->is_available)->toBeTrue();
})->with([Role::Waiter, Role::Cashier, Role::Rider]);
