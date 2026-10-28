<?php

use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\TableRequestType;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->withoutVite());

function floorStaff(Role $role = Role::Waiter): User
{
    return User::factory()->role($role)->create();
}

/** A table on a named spot of the grid, so adjacency is predictable. */
function floorTable(int $x, int $y = 0, TableStatus $status = TableStatus::Free, string $area = 'Main room', int $seats = 4): DiningTable
{
    return DiningTable::factory()->create(['x' => $x, 'y' => $y, 'width' => 2, 'height' => 2, 'status' => $status, 'area' => $area, 'seats' => $seats]);
}

it('lets floor roles in and keeps everyone else out', function (Role $role, bool $allowed) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get('/staff/floor')
        ->{$allowed ? 'assertOk' : 'assertForbidden'}();
})->with([
    [Role::Waiter, true],
    [Role::Cashier, true],
    [Role::Manager, true],
    [Role::Owner, true],
    [Role::Kitchen, false],
    [Role::Rider, false],
    [Role::Customer, false],
]);

it('keeps guests and non-floor roles from changing tables', function () {
    $table = floorTable(0);

    $this->post("/staff/floor/tables/{$table->id}/status", ['status' => 'seated', 'party_size' => 2])->assertRedirect('/login');
    $this->actingAs(floorStaff(Role::Kitchen))->post("/staff/floor/tables/{$table->id}/status", ['status' => 'seated', 'party_size' => 2])->assertForbidden();
    $this->actingAs(floorStaff(Role::Rider))->post('/staff/floor/merge', ['tables' => [1, 2]])->assertForbidden();
    expect($table->refresh()->status)->toBe(TableStatus::Free);
});

it('draws the floor with parties, requests and open orders', function () {
    $table = floorTable(0, 0, TableStatus::Seated);
    $other = floorTable(3);
    Order::factory()->dineIn()->create(['dining_table_id' => $table->id, 'total' => 2450, 'status' => OrderStatus::Cooking]);
    $table->requests()->create(['type' => TableRequestType::Bill]);
    Reservation::factory()->at(now()->addMinutes(40))->create(['dining_table_id' => $other->id, 'name' => 'Okafor']);

    $this->actingAs(floorStaff())->get('/staff/floor')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/floor/index')
            ->missing('tables')
            ->loadDeferredProps(fn (Assert $deferred) => $deferred
                ->where('tables.0.status', 'bill_requested')
                ->where('tables.0.open_total', 2450)
                ->where('tables.0.requests.0.type', 'bill')
                ->where('tables.0.party.kind', 'walk_in')
                ->where('upcoming.0.name', 'Okafor')
                ->where('upcoming.0.table_label', $other->label)));
});

it('seats a walk-in and logs it', function () {
    $table = floorTable(0, seats: 4);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post("/staff/floor/tables/{$table->id}/status", ['status' => 'seated', 'party_size' => 3])->assertRedirect();

    expect($table->refresh()->status)->toBe(TableStatus::Seated)
        ->and(ActivityLog::where('event', 'table.status')->where('user_id', $waiter->id)->count())->toBe(1);
});

it('refuses a walk-in without a party size or bigger than the table', function () {
    $table = floorTable(0, seats: 2);

    $this->actingAs(floorStaff())->post("/staff/floor/tables/{$table->id}/status", ['status' => 'seated'])->assertSessionHasErrors('party_size');
    $this->actingAs(floorStaff())->post("/staff/floor/tables/{$table->id}/status", ['status' => 'seated', 'party_size' => 5])->assertSessionHasErrors('party_size');
    expect($table->refresh()->status)->toBe(TableStatus::Free);
});

it('walks a table through bill, cleaning and free, and refuses jumps', function () {
    $table = floorTable(0, status: TableStatus::Seated);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post("/staff/floor/tables/{$table->id}/status", ['status' => 'free'])->assertSessionHasErrors('status');

    foreach (['bill_requested', 'needs_cleaning', 'free'] as $status) {
        $this->actingAs($waiter)->post("/staff/floor/tables/{$table->id}/status", ['status' => $status])->assertSessionHasNoErrors();
    }

    expect($table->refresh()->status)->toBe(TableStatus::Free);
});

it('handles a request and records who', function () {
    $table = floorTable(0, status: TableStatus::Seated);
    $request = $table->requests()->create(['type' => TableRequestType::Bill]);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post("/staff/floor/requests/{$request->id}/handle")->assertRedirect();

    expect($request->refresh()->handled_by)->toBe($waiter->id)
        ->and($request->handled_at)->not->toBeNull()
        ->and($table->refresh()->status)->toBe(TableStatus::BillRequested);

    $this->actingAs($waiter)->post("/staff/floor/requests/{$request->id}/handle")->assertSessionHasErrors('request');
});

it('merges neighbouring tables under the seated one and splits them again', function () {
    $free = floorTable(0);
    $seated = floorTable(3, status: TableStatus::Seated);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post('/staff/floor/merge', ['tables' => [$free->id, $seated->id]])->assertSessionHasNoErrors();

    expect($free->refresh()->merged_into_id)->toBe($seated->id)
        ->and($free->status)->toBe(TableStatus::Seated)
        ->and(ActivityLog::where('event', 'table.merged')->count())->toBe(1);

    $this->actingAs($waiter)->delete("/staff/floor/tables/{$seated->id}/merge")->assertSessionHasNoErrors();

    expect($free->refresh()->merged_into_id)->toBeNull()
        ->and($free->status)->toBe(TableStatus::Free)
        ->and(ActivityLog::where('event', 'table.unmerged')->count())->toBe(1);
});

it('refuses merges that break the rules', function (Closure $setup) {
    [$ids, $leadId] = $setup();

    $this->actingAs(floorStaff())->post('/staff/floor/merge', ['tables' => $ids, 'lead_id' => $leadId])->assertSessionHasErrors('tables');

    expect(DiningTable::whereNotNull('merged_into_id')->count())->toBe(0);
})->with([
    'tables that are not neighbours' => [fn () => [[floorTable(0)->id, floorTable(10)->id], null]],
    'different areas' => [fn () => [[floorTable(0)->id, floorTable(3, area: 'Garden')->id], null]],
    'two parties' => [fn () => [[floorTable(0, status: TableStatus::Seated)->id, floorTable(3, status: TableStatus::Seated)->id], null]],
    'a reserved table' => [fn () => [[floorTable(0)->id, floorTable(3, status: TableStatus::Reserved)->id], null]],
    'a free lead over a party' => [fn () => [[$a = floorTable(0)->id, floorTable(3, status: TableStatus::Seated)->id], $a]],
]);

it('will not merge a table that sits in someone else\'s merge', function () {
    $lead = floorTable(0);
    $joined = floorTable(3);
    $third = floorTable(6);
    $fourth = floorTable(9);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post('/staff/floor/merge', ['tables' => [$lead->id, $joined->id]])->assertSessionHasNoErrors();

    $this->actingAs($waiter)->post('/staff/floor/merge', ['tables' => [$joined->id, $third->id]])->assertSessionHasErrors('tables');

    $this->actingAs($waiter)->post('/staff/floor/merge', ['tables' => [$lead->id, $fourth->id], 'lead_id' => $fourth->id])->assertSessionHasErrors('tables');

    $this->actingAs($waiter)->post('/staff/floor/merge', ['tables' => [$lead->id, $third->id]])->assertSessionHasNoErrors();
    expect($third->refresh()->merged_into_id)->toBe($lead->id);
});

it('moves a party and its open orders to a free table', function () {
    $from = floorTable(0, status: TableStatus::Seated);
    $to = floorTable(3);
    $open = Order::factory()->dineIn()->create(['dining_table_id' => $from->id, 'status' => OrderStatus::Cooking]);
    $done = Order::factory()->dineIn()->create(['dining_table_id' => $from->id, 'status' => OrderStatus::Completed]);
    $request = $from->requests()->create(['type' => TableRequestType::CallWaiter]);

    $this->actingAs(floorStaff())->post("/staff/floor/tables/{$from->id}/transfer", ['to' => $to->id])->assertSessionHasNoErrors();

    expect($open->refresh()->dining_table_id)->toBe($to->id)
        ->and($done->refresh()->dining_table_id)->toBe($from->id)
        ->and($request->refresh()->dining_table_id)->toBe($to->id)
        ->and($to->refresh()->status)->toBe(TableStatus::Seated)
        ->and($from->refresh()->status)->toBe(TableStatus::NeedsCleaning)
        ->and(ActivityLog::where('event', 'table.transferred')->count())->toBe(1);
});

it('refuses to move a party onto a busy table or from an empty one', function () {
    $from = floorTable(0, status: TableStatus::Seated);
    $busy = floorTable(3, status: TableStatus::Seated);
    $free = floorTable(6);
    $order = Order::factory()->dineIn()->create(['dining_table_id' => $from->id, 'status' => OrderStatus::Cooking]);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post("/staff/floor/tables/{$from->id}/transfer", ['to' => $busy->id])->assertSessionHasErrors('to');
    $this->actingAs($waiter)->post("/staff/floor/tables/{$free->id}/transfer", ['to' => $from->id])->assertSessionHasErrors('to');
    $this->actingAs($waiter)->post("/staff/floor/tables/{$from->id}/transfer", ['to' => $from->id])->assertSessionHasErrors('to');

    expect($order->refresh()->dining_table_id)->toBe($from->id);
});

it('seats a booking on its own table', function () {
    $table = floorTable(0, status: TableStatus::Reserved);
    $booking = Reservation::factory()->at(now()->addMinutes(10))->create(['dining_table_id' => $table->id, 'party_size' => 3]);

    $this->actingAs(floorStaff())->post("/staff/floor/reservations/{$booking->id}/seat")->assertSessionHasNoErrors();

    expect($booking->refresh()->status)->toBe(ReservationStatus::Seated)
        ->and($booking->seated_at)->not->toBeNull()
        ->and($table->refresh()->status)->toBe(TableStatus::Seated);
});

it('seats an unassigned booking on a chosen free table and refuses busy or small ones', function () {
    $booking = Reservation::factory()->at(now()->addMinutes(10))->create(['party_size' => 4]);
    $busy = floorTable(0, status: TableStatus::Seated);
    $small = floorTable(3, seats: 2);
    $good = floorTable(6, seats: 4);
    $waiter = floorStaff();

    $this->actingAs($waiter)->post("/staff/floor/reservations/{$booking->id}/seat")->assertSessionHasErrors('reservation');
    $this->actingAs($waiter)->post("/staff/floor/reservations/{$booking->id}/seat", ['table_id' => $busy->id])->assertSessionHasErrors('reservation');
    $this->actingAs($waiter)->post("/staff/floor/reservations/{$booking->id}/seat", ['table_id' => $small->id])->assertSessionHasErrors('reservation');
    $this->actingAs($waiter)->post("/staff/floor/reservations/{$booking->id}/seat", ['table_id' => $good->id])->assertSessionHasNoErrors();

    expect($booking->refresh()->dining_table_id)->toBe($good->id)
        ->and($good->refresh()->status)->toBe(TableStatus::Seated);
});

it('will not seat a booking whose deposit is unpaid', function () {
    $table = floorTable(0, seats: 8);
    $booking = Reservation::factory()->pendingDeposit(6)->at(now()->addMinutes(10))->create(['dining_table_id' => $table->id]);

    $this->actingAs(floorStaff())->post("/staff/floor/reservations/{$booking->id}/seat")->assertSessionHasErrors('reservation');
    expect($table->refresh()->status)->toBe(TableStatus::Free);
});
