<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Enums\TableStatus;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // A Tuesday: lunch 12:00-15:00 and dinner 17:30-22:30.
    $this->travelTo(CarbonImmutable::parse('2030-03-05 18:00', 'Europe/London'));
    $this->two = DiningTable::factory()->seats(2)->create(['label' => 'T1', 'area' => 'Main room']);
    $this->four = DiningTable::factory()->seats(4)->create(['label' => 'T2', 'area' => 'Main room']);
    $this->six = DiningTable::factory()->seats(6)->create(['label' => 'T3', 'area' => 'Terrace']);
    $this->waiter = User::factory()->role(Role::Waiter)->create();
    $this->manager = User::factory()->role(Role::Manager)->create();
});

function bookAt(string $time, array $attributes = [], string $date = '2030-03-05'): Reservation
{
    return Reservation::factory()->at(CarbonImmutable::parse("$date $time", 'Europe/London'))->create($attributes);
}

function staffBooking(array $overrides = []): array
{
    return [
        'party_size' => 2,
        'date' => '2030-03-06',
        'time' => '19:00',
        'name' => 'Asha Rahman',
        'phone' => '07700 900123',
        'notes' => 'Nut allergy',
        ...$overrides,
    ];
}

it('lets front of house in and keeps the kitchen, riders, customers and guests out', function (Role $role, int $status) {
    $this->actingAs(User::factory()->role($role)->create())->get('/staff/reservations')->assertStatus($status);
})->with([
    [Role::Waiter, 200], [Role::Cashier, 200], [Role::Manager, 200], [Role::Owner, 200],
    [Role::Kitchen, 403], [Role::Rider, 403], [Role::Customer, 403],
]);

it('sends guests to sign in', function () {
    $this->get('/staff/reservations')->assertRedirect('/login');
});

it('keeps the kitchen from changing bookings', function () {
    $booking = bookAt('19:00', ['dining_table_id' => $this->four->id]);
    $kitchen = User::factory()->role(Role::Kitchen)->create();

    $this->actingAs($kitchen)->post("/staff/reservations/{$booking->id}/status", ['action' => 'seat'])->assertForbidden();
    $this->actingAs($kitchen)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->six->id])->assertForbidden();
    $this->actingAs($kitchen)->post('/staff/reservations', staffBooking())->assertForbidden();
});

it('shows a day as tables, opening periods, bookings and service totals', function () {
    $lunch = bookAt('12:30', ['party_size' => 3, 'dining_table_id' => $this->four->id]);
    bookAt('19:00', ['party_size' => 2, 'dining_table_id' => $this->two->id, 'email' => 'regular@example.com', 'occasion' => 'birthday']);
    bookAt('19:30', ['party_size' => 4, 'dining_table_id' => $this->four->id, 'status' => ReservationStatus::Cancelled]);
    bookAt('19:00', ['dining_table_id' => $this->two->id], '2030-03-06');
    Reservation::factory()->completed()->create(['email' => 'regular@example.com', 'starts_at' => '2030-02-20 19:00', 'ends_at' => '2030-02-20 20:45']);

    $this->actingAs($this->waiter)->get('/staff/reservations?date=2030-03-05')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('staff/reservations/index')
            ->where('view', 'day')
            ->where('date', '2030-03-05')
            ->where('is_today', true)
            ->where('weekday', 'Tuesday')
            ->where('prev', '2030-03-04')
            ->where('next', '2030-03-06')
            ->has('tables', 3)
            ->where('periods', [['start' => '12:00', 'end' => '15:00'], ['start' => '17:30', 'end' => '22:30']])
            ->has('bookings', 2)
            ->where('bookings.0.id', $lunch->id)
            ->where('bookings.0.start', '12:30')
            ->where('bookings.0.table_label', 'T2')
            ->where('bookings.1.previous_visits', 1)
            ->where('bookings.1.occasion', 'birthday')
            ->where('services.0.key', 'lunch')
            ->where('services.0.bookings', 1)
            ->where('services.0.covers', 3)
            ->where('services.1.key', 'dinner')
            ->where('services.1.covers', 2)
            ->where('services.1.walk_in.tables', 2)
            ->where('can_waive_deposit', false));
});

it('offers only tables free for the whole booking to move to', function () {
    $moving = bookAt('19:00', ['party_size' => 2, 'dining_table_id' => $this->two->id]);
    bookAt('20:00', ['party_size' => 4, 'dining_table_id' => $this->four->id]);

    $this->actingAs($this->waiter)->get('/staff/reservations?date=2030-03-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('bookings.0.id', $moving->id)
            ->where('bookings.0.free_table_ids', [$this->two->id, $this->six->id]));
});

it('shows a week with covers and bookings per day', function () {
    bookAt('19:00', ['party_size' => 4, 'dining_table_id' => $this->four->id]);
    bookAt('20:00', ['party_size' => 2, 'dining_table_id' => $this->two->id]);
    bookAt('19:00', ['party_size' => 5, 'dining_table_id' => $this->six->id, 'status' => ReservationStatus::NoShow], '2030-03-07');

    $this->actingAs($this->waiter)->get('/staff/reservations?view=week&date=2030-03-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'week')
            ->where('prev', '2030-02-26')
            ->has('week', 7)
            ->where('week.0.date', '2030-03-04')
            ->where('week.0.closed', true)
            ->where('week.1.is_today', true)
            ->where('week.1.bookings', 2)
            ->where('week.1.covers', 6)
            ->where('week.3.bookings', 0)
            ->where('bookings', []));
});

it('falls back to today for a bad date', function () {
    $this->actingAs($this->waiter)->get('/staff/reservations?date=nonsense')
        ->assertInertia(fn (Assert $page) => $page->where('date', '2030-03-05'));
});

it('lets a manager take the deposit in person with a reference, and logs it', function () {
    $booking = Reservation::factory()->pendingDeposit(6)->at('2030-03-06 19:00')->create(['dining_table_id' => $this->six->id]);

    $this->actingAs($this->manager)->post("/staff/reservations/{$booking->id}/status", ['action' => 'confirm', 'reason' => 'Terminal receipt 4821'])->assertRedirect();

    expect($booking->fresh())
        ->status->toBe(ReservationStatus::Confirmed)
        ->deposit_status->toBe(PaymentStatus::Paid);
    $entry = ActivityLog::where('event', 'reservation.deposit_taken')->sole();
    expect($entry->properties['reference'])->toBe('Terminal receipt 4821')->and($entry->user_id)->toBe($this->manager->id);
});

it('needs a note or reference to take a deposit', function () {
    $booking = Reservation::factory()->pendingDeposit(6)->at('2030-03-06 19:00')->create(['dining_table_id' => $this->six->id]);

    $this->actingAs($this->manager)->post("/staff/reservations/{$booking->id}/status", ['action' => 'confirm'])->assertSessionHasErrors('reason');

    expect($booking->fresh()->status)->toBe(ReservationStatus::PendingDeposit);
});

it('keeps deposits for management only', function (Role $role, bool $allowed) {
    $booking = Reservation::factory()->pendingDeposit(6)->at('2030-03-06 19:00')->create(['dining_table_id' => $this->six->id]);

    $response = $this->actingAs(User::factory()->role($role)->create())->post("/staff/reservations/{$booking->id}/status", ['action' => 'confirm', 'reason' => 'Receipt 1']);

    $allowed ? $response->assertRedirect() : $response->assertForbidden();
    expect($booking->fresh()->status)->toBe($allowed ? ReservationStatus::Confirmed : ReservationStatus::PendingDeposit);
})->with([
    [Role::Waiter, false],
    [Role::Cashier, false],
    [Role::Manager, true],
    [Role::Owner, true],
]);

it('seats a booking and marks its table seated', function () {
    $booking = bookAt('18:00', ['dining_table_id' => $this->two->id]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'seat'])->assertRedirect();

    expect($booking->fresh())->status->toBe(ReservationStatus::Seated)->seated_at->not->toBeNull();
    expect($this->two->fresh())->status->toBe(TableStatus::Seated)->party_size->toBe($booking->party_size)->seated_at->not->toBeNull();
    expect(ActivityLog::where('event', 'reservation.seated')->count())->toBe(1);
});

it('will not seat a booking twice', function () {
    $booking = bookAt('18:00', ['dining_table_id' => $this->two->id]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'seat'])->assertSessionHasNoErrors();
    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'seat'])->assertSessionHasErrors('action');
    expect(ActivityLog::where('event', 'reservation.seated')->count())->toBe(1);
});

it('will not seat a booking onto a table someone else is already sitting at', function () {
    $this->two->update(['status' => TableStatus::Seated]);
    $booking = bookAt('18:00', ['party_size' => 2, 'dining_table_id' => $this->two->id]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'seat'])->assertSessionHasErrors('action');
    expect($booking->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('will not seat a booking without a table or one still owing a deposit', function () {
    $noTable = bookAt('18:00');
    $owing = Reservation::factory()->pendingDeposit()->at('2030-03-05 18:00')->create(['dining_table_id' => $this->six->id]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$noTable->id}/status", ['action' => 'seat'])->assertSessionHasErrors('action');
    $this->actingAs($this->waiter)->post("/staff/reservations/{$owing->id}/status", ['action' => 'seat'])->assertSessionHasErrors('action');

    expect($noTable->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($owing->fresh()->status)->toBe(ReservationStatus::PendingDeposit);
});

it('finishes a seated booking and flags the table for cleaning', function () {
    $booking = Reservation::factory()->seated()->create(['dining_table_id' => $this->four->id]);
    $this->four->update(['status' => TableStatus::Seated]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'complete'])->assertRedirect();

    expect($booking->fresh()->status)->toBe(ReservationStatus::Completed)
        ->and($this->four->fresh()->status)->toBe(TableStatus::NeedsCleaning);
});

it('carries a seated party to the new table when it moves', function () {
    $booking = Reservation::factory()->seated()->create(['dining_table_id' => $this->two->id, 'party_size' => 2]);
    $this->two->update(['status' => TableStatus::Seated, 'party_size' => 2, 'seated_at' => now()]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->six->id])->assertRedirect();

    expect($this->six->fresh())->status->toBe(TableStatus::Seated)->party_size->toBe(2)
        ->and($this->two->fresh())->status->toBe(TableStatus::NeedsCleaning)->party_size->toBeNull();
});

it('will not move a seated party onto a table that is not free', function () {
    $booking = Reservation::factory()->seated()->create(['dining_table_id' => $this->two->id, 'party_size' => 2]);
    $this->two->update(['status' => TableStatus::Seated]);
    $this->six->update(['status' => TableStatus::Seated]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->six->id])->assertSessionHasErrors('dining_table_id');
    expect($booking->fresh()->dining_table_id)->toBe($this->two->id);
});

it('will not move a booking that has finished since the page loaded', function () {
    $booking = bookAt('19:00', ['party_size' => 2, 'dining_table_id' => $this->two->id, 'status' => ReservationStatus::Cancelled]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->six->id])->assertSessionHasErrors('dining_table_id');
    expect($booking->fresh()->dining_table_id)->toBe($this->two->id);
});

it('only allows a no-show once the booking has started, and logs it', function () {
    $later = bookAt('19:00', ['dining_table_id' => $this->four->id]);
    $started = bookAt('17:30', ['dining_table_id' => $this->two->id]);
    $this->two->update(['status' => TableStatus::Reserved]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$later->id}/status", ['action' => 'no_show'])->assertSessionHasErrors('action');
    expect($later->fresh()->status)->toBe(ReservationStatus::Confirmed);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$started->id}/status", ['action' => 'no_show', 'reason' => 'Phone off'])->assertRedirect();

    expect($started->fresh()->status)->toBe(ReservationStatus::NoShow)
        ->and($this->two->fresh()->status)->toBe(TableStatus::Free);
    $log = ActivityLog::where('event', 'reservation.no_show')->sole();
    expect($log->user_id)->toBe($this->waiter->id)
        ->and($log->properties['reason'])->toBe('Phone off');
});

it('cancels with a reason and logs it', function () {
    $booking = bookAt('19:00', ['dining_table_id' => $this->four->id]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'cancel'])->assertSessionHasErrors('reason');
    expect($booking->fresh()->status)->toBe(ReservationStatus::Confirmed);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$booking->id}/status", ['action' => 'cancel', 'reason' => 'Guest rang'])->assertRedirect();

    expect($booking->fresh()->status)->toBe(ReservationStatus::Cancelled);
    expect(ActivityLog::where('event', 'reservation.cancelled')->sole()->description)->toContain('Guest rang');
});

it('rejects unknown actions and finished bookings', function () {
    $done = Reservation::factory()->completed()->create(['dining_table_id' => $this->four->id]);

    $this->actingAs($this->waiter)->post("/staff/reservations/{$done->id}/status", ['action' => 'explode'])->assertSessionHasErrors('action');
    $this->actingAs($this->waiter)->post("/staff/reservations/{$done->id}/status", ['action' => 'cancel', 'reason' => 'x'])->assertSessionHasErrors('action');
});

it('moves a booking to a free table', function () {
    $booking = bookAt('19:00', ['party_size' => 2, 'dining_table_id' => $this->two->id]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->six->id])->assertRedirect();

    expect($booking->fresh()->dining_table_id)->toBe($this->six->id);
    expect(ActivityLog::where('event', 'reservation.moved')->count())->toBe(1);
});

it('refuses to move a booking onto a table that is booked at an overlapping time', function () {
    $booking = bookAt('19:00', ['party_size' => 2, 'dining_table_id' => $this->two->id]);
    bookAt('20:30', ['party_size' => 4, 'dining_table_id' => $this->four->id]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->four->id])
        ->assertSessionHasErrors('dining_table_id');

    expect($booking->fresh()->dining_table_id)->toBe($this->two->id);
});

it('allows a move onto a table whose booking ends before this one starts', function () {
    $booking = bookAt('19:00', ['party_size' => 2, 'dining_table_id' => $this->two->id]);
    bookAt('16:30', ['party_size' => 4, 'dining_table_id' => $this->four->id]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->four->id])->assertRedirect();

    expect($booking->fresh()->dining_table_id)->toBe($this->four->id);
});

it('refuses a table too small for the party', function () {
    $booking = bookAt('19:00', ['party_size' => 4, 'dining_table_id' => $this->four->id]);

    $this->actingAs($this->waiter)->patch("/staff/reservations/{$booking->id}/table", ['dining_table_id' => $this->two->id])
        ->assertSessionHasErrors('dining_table_id');
});

it('takes a phone booking onto a free table without needing an email', function () {
    $this->actingAs($this->waiter)->post('/staff/reservations', staffBooking())
        ->assertRedirect('/staff/reservations?date=2030-03-06');

    $booking = Reservation::sole();
    expect($booking)
        ->name->toBe('Asha Rahman')
        ->email->toBe('')
        ->status->toBe(ReservationStatus::Confirmed)
        ->dining_table_id->toBe($this->two->id)
        ->user_id->toBeNull();
    expect(ActivityLog::where('event', 'reservation.created')->count())->toBe(1);
});

it('refuses a phone booking when no table is free or the time is not offered', function () {
    bookAt('19:00', ['dining_table_id' => $this->two->id], '2030-03-06');
    bookAt('19:00', ['party_size' => 4, 'dining_table_id' => $this->four->id], '2030-03-06');

    $this->actingAs($this->waiter)->post('/staff/reservations', staffBooking())->assertSessionHasErrors('time');
    $this->actingAs($this->waiter)->post('/staff/reservations', staffBooking(['time' => '16:00', 'party_size' => 4]))->assertSessionHasErrors('time');
    $this->actingAs($this->waiter)->post('/staff/reservations', staffBooking(['party_size' => 9]))->assertSessionHasErrors('party_size');

    expect(Reservation::count())->toBe(2);
});

it('asks for a deposit on a big party unless a manager waives it', function () {
    $this->actingAs($this->waiter)->post('/staff/reservations', staffBooking(['party_size' => 6]))->assertRedirect();
    $owing = Reservation::sole();
    expect($owing)->status->toBe(ReservationStatus::PendingDeposit)->deposit->toBe(6000)->deposit_status->toBe(PaymentStatus::Pending);

    $this->actingAs($this->manager)->post('/staff/reservations', staffBooking(['party_size' => 6, 'date' => '2030-03-07', 'name' => 'Waived Party', 'waive_deposit' => true]))->assertRedirect();
    $waived = Reservation::where('name', 'Waived Party')->sole();
    expect($waived)->status->toBe(ReservationStatus::Confirmed)->deposit->toBe(0)->deposit_status->toBeNull();
    expect(ActivityLog::where('event', 'reservation.created')->latest('id')->first()->properties)->toBe(['deposit_waived' => true]);
});

it('does not let waiters or cashiers waive a deposit', function (Role $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->post('/staff/reservations', staffBooking(['party_size' => 6, 'waive_deposit' => true]))
        ->assertSessionHasErrors('waive_deposit');

    expect(Reservation::count())->toBe(0);
})->with([Role::Waiter, Role::Cashier]);

it('lets the owner waive a deposit', function () {
    $this->actingAs(User::factory()->role(Role::Owner)->create())
        ->post('/staff/reservations', staffBooking(['party_size' => 6, 'waive_deposit' => true]))->assertRedirect();

    expect(Reservation::sole()->status)->toBe(ReservationStatus::Confirmed);
});

it('tells managers they may waive deposits', function () {
    $this->actingAs($this->manager)->get('/staff/reservations')
        ->assertInertia(fn (Assert $page) => $page->where('can_waive_deposit', true));
});
