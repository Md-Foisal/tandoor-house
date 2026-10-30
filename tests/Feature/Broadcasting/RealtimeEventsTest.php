<?php

use App\Actions\Floor\HandleTableRequest;
use App\Actions\Floor\SeatReservation;
use App\Broadcasting\Channels;
use App\Enums\LineStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\TableRequestType;
use App\Events\KitchenLineUpdated;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Events\ReservationUpdated;
use App\Events\TableRequestRaised;
use App\Http\Controllers\Staff\Reservations\ReservationActions;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use App\Services\OrderStateMachine;
use Carbon\CarbonImmutable;
use Database\Seeders\MenuSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

const REALTIME_EVENTS = [OrderPlaced::class, OrderStatusChanged::class, KitchenLineUpdated::class, TableRequestRaised::class, ReservationUpdated::class];

beforeEach(function () {
    $this->seed(MenuSeeder::class);
    $this->machine = app(OrderStateMachine::class);
});

function realtimeOrder(string $state = 'delivery', array $attributes = []): Order
{
    return Order::factory()->{$state}()->withLines(2)->create([
        'status' => OrderStatus::Placed,
        'accepted_at' => null,
        'ready_at' => null,
        'completed_at' => null,
        ...$attributes,
    ])->fresh();
}

/**
 * @return list<string>
 */
function channelNames(object $event): array
{
    return array_map(fn (PrivateChannel $channel) => $channel->name, $event->broadcastOn());
}

it('announces every order transition with where it came from and went to', function () {
    Event::fake(REALTIME_EVENTS);
    $order = realtimeOrder();
    $path = [OrderStatus::Accepted, OrderStatus::Cooking, OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::Delivered, OrderStatus::Completed];

    foreach ($path as $to) {
        $this->machine->transition($order, $to);
    }

    $from = OrderStatus::Placed;

    foreach ($path as $to) {
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->number === $order->number && $event->from === $from && $event->to === $to);
        $from = $to;
    }

    Event::assertDispatchedTimes(OrderStatusChanged::class, count($path));
});

it('announces nothing when a transition is refused', function () {
    Event::fake(REALTIME_EVENTS);

    expect(fn () => $this->machine->transition(realtimeOrder(), OrderStatus::Ready))->toThrow(ValidationException::class);

    Event::assertNothingDispatched();
});

it('announces kitchen bumps and recalls, and the order moving with them', function () {
    Event::fake(REALTIME_EVENTS);
    $order = realtimeOrder();
    $this->machine->transition($order, OrderStatus::Accepted);
    [$first, $second] = $order->lines()->get()->all();

    $this->machine->bump($first);
    $this->machine->bump($second);
    $this->machine->recall($second->fresh());

    Event::assertDispatched(KitchenLineUpdated::class, fn (KitchenLineUpdated $event) => $event->lineId === $first->id && $event->status === LineStatus::Ready);
    Event::assertDispatched(KitchenLineUpdated::class, fn (KitchenLineUpdated $event) => $event->lineId === $second->id && $event->status === LineStatus::Cooking);
    Event::assertDispatchedTimes(KitchenLineUpdated::class, 3);
    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->from === OrderStatus::Accepted && $event->to === OrderStatus::Cooking);
    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->from === OrderStatus::Cooking && $event->to === OrderStatus::Ready);
    Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->from === OrderStatus::Ready && $event->to === OrderStatus::Cooking);
});

it('sends status changes to the rider on the run and to the floor for tables', function () {
    $rider = User::factory()->role(Role::Rider)->create();
    $delivery = realtimeOrder('delivery', ['rider_id' => $rider->id]);
    $dineIn = realtimeOrder('dineIn');

    expect(channelNames(new OrderStatusChanged($delivery, OrderStatus::Ready, OrderStatus::OutForDelivery)))
        ->toContain('private-'.Channels::STAFF_ORDERS, 'private-'.Channels::STAFF_KITCHEN, 'private-'.Channels::orderName($delivery), 'private-'.Channels::riderName($rider->id))
        ->not->toContain('private-'.Channels::STAFF_FLOOR)
        ->and(channelNames(new OrderStatusChanged($dineIn, OrderStatus::Ready, OrderStatus::Served)))
        ->toContain('private-'.Channels::STAFF_FLOOR)
        ->and(channelNames(new OrderPlaced($dineIn)))
        ->toBe(['private-staff.orders', 'private-staff.kitchen', 'private-staff.floor']);
});

it('announces orders placed at checkout and from the table', function () {
    Event::fake(REALTIME_EVENTS);
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->travelTo(CarbonImmutable::parse('2026-10-27 19:00', 'Europe/London'));
    DeliveryZone::factory()->covering(['E1'])->create(['minimum_order' => 0, 'fee' => 249]);
    $item = MenuItem::query()->where('is_available', true)->where('is_alcoholic', false)
        ->doesntHave('variants')->doesntHave('modifierGroups')->firstOrFail();

    $this->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => 2])->assertSessionHasNoErrors();
    $this->post(route('checkout.store'), [
        'channel' => 'delivery',
        'name' => 'Asha Rahman',
        'email' => 'asha@example.com',
        'phone' => '07700 900123',
        'line1' => '5 Fournier Street',
        'city' => 'London',
        'postcode' => 'E1 6QR',
        'payment_method' => 'cash',
    ])->assertSessionHasNoErrors();

    $table = DiningTable::factory()->create();
    $this->get(route('table.show', $table->qr_token));
    $this->post(route('basket.lines.store'), ['menu_item_id' => $item->id, 'quantity' => 1])->assertSessionHasNoErrors();
    $this->post(route('table.order', $table->qr_token))->assertSessionHasNoErrors();

    $numbers = Order::query()->orderBy('id')->pluck('number')->all();
    expect($numbers)->toHaveCount(2);

    foreach ($numbers as $number) {
        Event::assertDispatched(OrderPlaced::class, fn (OrderPlaced $event) => $event->number === $number);
    }
});

it('announces table calls when raised and when handled', function () {
    Event::fake(REALTIME_EVENTS);
    $table = DiningTable::factory()->seated()->create();

    $this->post(route('table.request', $table->qr_token), ['type' => 'bill'])->assertSessionHasNoErrors();
    $request = $table->requests()->firstOrFail();
    app(HandleTableRequest::class)->handle($request, User::factory()->role(Role::Waiter)->create());

    Event::assertDispatched(TableRequestRaised::class, fn (TableRequestRaised $event) => $event->requestId === $request->id && $event->type === TableRequestType::Bill && ! $event->handled);
    Event::assertDispatched(TableRequestRaised::class, fn (TableRequestRaised $event) => $event->requestId === $request->id && $event->handled);
});

it('announces bookings made, seated, cancelled and missed', function () {
    Event::fake(REALTIME_EVENTS);
    $this->travelTo(CarbonImmutable::parse('2030-03-04 10:00', 'Europe/London'));
    $manager = User::factory()->role(Role::Manager)->create();
    DiningTable::factory()->seats(2)->create();

    $this->post('/book', [
        'party_size' => 2,
        'date' => '2030-03-06',
        'time' => '19:00',
        'name' => 'Asha Rahman',
        'email' => 'asha@example.com',
        'phone' => '07700 900123',
    ])->assertSessionHasNoErrors();
    $booked = Reservation::query()->firstOrFail();

    $table = DiningTable::factory()->seats(4)->create();
    $seated = Reservation::factory()->create();
    app(SeatReservation::class)->handle($seated, $table->id, $manager);

    $cancelled = Reservation::factory()->create();
    app(ReservationActions::class)->apply($cancelled, 'cancel', $manager, 'Called to cancel');

    $missed = Reservation::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addMinutes(45)]);
    app(ReservationActions::class)->apply($missed, 'no_show', $manager);

    Event::assertDispatched(ReservationUpdated::class, fn (ReservationUpdated $event) => $event->reservationId === $booked->id && $event->status === ReservationStatus::Confirmed);
    Event::assertDispatched(ReservationUpdated::class, fn (ReservationUpdated $event) => $event->reservationId === $seated->id && $event->status === ReservationStatus::Seated);
    Event::assertDispatched(ReservationUpdated::class, fn (ReservationUpdated $event) => $event->reservationId === $cancelled->id && $event->status === ReservationStatus::Cancelled);
    Event::assertDispatched(ReservationUpdated::class, fn (ReservationUpdated $event) => $event->reservationId === $missed->id && $event->status === ReservationStatus::NoShow);
});

it('never puts personal details in a broadcast payload', function () {
    $customer = User::factory()->create(['name' => 'Priya Kaur', 'email' => 'priya@example.com', 'phone' => '+447700900456']);
    $order = realtimeOrder('delivery', ['user_id' => $customer->id, 'contact' => ['name' => 'Priya Kaur', 'email' => 'priya@example.com', 'phone' => '+447700900456'], 'notes' => 'Ring twice']);
    $table = DiningTable::factory()->create();
    $request = $table->requests()->create(['type' => TableRequestType::CallWaiter]);
    $reservation = Reservation::factory()->create(['name' => 'Priya Kaur', 'email' => 'priya@example.com', 'phone' => '+447700900456', 'notes' => 'Nut allergy']);

    $payloads = json_encode([
        (new OrderPlaced($order))->broadcastWith(),
        (new OrderStatusChanged($order, OrderStatus::Placed, OrderStatus::Accepted))->broadcastWith(),
        (new KitchenLineUpdated($order->lines()->firstOrFail(), $order))->broadcastWith(),
        (new TableRequestRaised($request))->broadcastWith(),
        (new ReservationUpdated($reservation))->broadcastWith(),
    ]);

    foreach (['Priya', 'priya@example.com', '7700900456', 'Ring twice', 'Nut allergy', $order->postcode, $order->address['line1']] as $personal) {
        expect($payloads)->not->toContain($personal);
    }
});

it('broadcasts through the real queue and broadcaster to the log', function () {
    config(['broadcasting.default' => 'log']);
    app(BroadcastManager::class)->forgetDrivers();
    Notification::fake();
    Log::spy();
    $order = realtimeOrder();

    $this->machine->transition($order, OrderStatus::Accepted);

    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'Broadcasting [order.status]')
        && str_contains($message, 'private-orders.'.$order->number)
        && str_contains($message, '"to": "accepted"'))->once();
});
