<?php

use App\Actions\Deliveries\AssignRider;
use App\Actions\Floor\ChangeTableStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Events\RiderAssigned;
use App\Events\TableStatusChanged;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed(MenuSeeder::class);
});

function readyDelivery(?User $rider = null): Order
{
    return Order::factory()->delivery()->withLines(1)->create([
        'status' => OrderStatus::Ready,
        'rider_id' => $rider?->id,
    ])->fresh();
}

it('announces a rider assignment to the board and both riders', function () {
    Event::fake([RiderAssigned::class]);
    $manager = User::factory()->role(Role::Manager)->create();
    $old = User::factory()->role(Role::Rider)->create();
    $new = User::factory()->role(Role::Rider)->create();
    $order = readyDelivery($old);

    app(AssignRider::class)($order, $new, $manager);

    Event::assertDispatched(RiderAssigned::class, function (RiderAssigned $event) use ($order, $old, $new) {
        expect(collect($event->broadcastOn())->map->name->all())
            ->toBe(['private-staff.orders', "private-staff.deliveries.{$new->id}", "private-staff.deliveries.{$old->id}"])
            ->and($event->broadcastAs())->toBe('rider.assigned')
            ->and($event->broadcastWith())->toBe([
                'id' => $order->id,
                'number' => $order->number,
                'rider_id' => $new->id,
                'previous_rider_id' => $old->id,
            ]);

        return true;
    });
});

it('announces a rider being removed, and stays quiet when nothing changed', function () {
    Event::fake([RiderAssigned::class]);
    $manager = User::factory()->role(Role::Manager)->create();
    $rider = User::factory()->role(Role::Rider)->create();
    $order = readyDelivery($rider);

    app(AssignRider::class)($order, $rider, $manager);
    Event::assertNotDispatched(RiderAssigned::class);

    app(AssignRider::class)($order, null, $manager);
    Event::assertDispatched(RiderAssigned::class, fn (RiderAssigned $event) => $event->riderId === null
        && $event->previousRiderId === $rider->id
        && count($event->broadcastOn()) === 2);
});

it('announces nothing when the assignment is refused', function () {
    Event::fake([RiderAssigned::class]);
    $waiter = User::factory()->role(Role::Waiter)->create();
    $rider = User::factory()->role(Role::Rider)->create();

    expect(fn () => app(AssignRider::class)(readyDelivery(), $rider, $waiter))->toThrow(Exception::class);

    Event::assertNotDispatched(RiderAssigned::class);
});

it('announces table status changes on the floor channel', function () {
    Event::fake([TableStatusChanged::class]);
    $waiter = User::factory()->role(Role::Waiter)->create();
    $table = DiningTable::factory()->seated()->create();

    app(ChangeTableStatus::class)->handle($table, TableStatus::BillRequested, $waiter);

    Event::assertDispatched(TableStatusChanged::class, function (TableStatusChanged $event) use ($table) {
        expect(collect($event->broadcastOn())->map->name->all())->toBe(['private-staff.floor'])
            ->and($event->broadcastAs())->toBe('table.status')
            ->and($event->broadcastWith())->toBe(['id' => $table->id, 'from' => 'seated', 'to' => 'bill_requested']);

        return true;
    });
});

it('announces nothing for a table move that is not allowed', function () {
    Event::fake([TableStatusChanged::class]);
    $waiter = User::factory()->role(Role::Waiter)->create();
    $table = DiningTable::factory()->create();

    expect(fn () => app(ChangeTableStatus::class)->handle($table, TableStatus::NeedsCleaning, $waiter))->toThrow(Exception::class);

    Event::assertNotDispatched(TableStatusChanged::class);
});
