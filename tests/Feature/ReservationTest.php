<?php

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Http\Controllers\ReservationController;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use App\Services\TableAvailability;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2030-03-04 10:00', 'Europe/London')); // a Monday
    // A party takes a table with at most two spare seats, so a two and a six never compete.
    $this->twoTop = DiningTable::factory()->seats(2)->create();
    $this->sixTop = DiningTable::factory()->seats(6)->create();
});

function bookingPayload(array $overrides = []): array
{
    return [
        'party_size' => 2,
        'date' => '2030-03-06',
        'time' => '19:00',
        'name' => 'Asha Rahman',
        'email' => 'asha@example.com',
        'phone' => '07700 900123',
        'occasion' => 'birthday',
        'notes' => 'Nut allergy',
        'marketing_opt_in' => false,
        ...$overrides,
    ];
}

function slotsFor(string $date, int $party): array
{
    return app(TableAvailability::class)->slots(CarbonImmutable::parse($date, 'Europe/London'), $party);
}

it('shows the booking page with the next sixty days', function () {
    $this->get('/book')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('book/create')
            ->has('days', 60)
            ->where('days.0.status', 'closed')
            ->where('query.party', 2)
            ->where('query.date', '2030-03-05')
            ->where('rules.max_party', 10)
            ->has('slots'));
});

it('prefills signed-in customers', function () {
    $user = User::factory()->create(['phone' => '+447700900123']);

    $this->actingAs($user)->get('/book')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contact.email', $user->email)
            ->where('contact.phone', '+447700900123'));
});

it('leaves out closed Mondays and past times', function () {
    expect(slotsFor('2030-03-04', 2))->toBe([]);

    // Tuesday opens 12:00; at 17:00 today only dinner (after the 30 minute lead) remains.
    $this->travelTo(CarbonImmutable::parse('2030-03-05 17:00', 'Europe/London'));
    $times = array_column(slotsFor('2030-03-05', 2), 'time');

    expect($times)->not->toContain('12:00')
        ->and($times)->not->toContain('17:15')
        ->and($times[0])->toBe('17:30')
        ->and(end($times))->toBe('20:45');
});

it('ends the last seating by closing time', function () {
    $times = array_column(slotsFor('2030-03-08', 2), 'time'); // Friday 12:00-23:00

    expect(end($times))->toBe('21:15');
});

it('serves slots as json', function () {
    $this->getJson('/book/slots?party=2&date=2030-03-06')
        ->assertOk()
        ->assertJsonPath('slots.0.time', '12:00')
        ->assertJsonPath('slots.0.available', true);

    $this->getJson('/book/slots?party=11&date=2030-03-06')->assertUnprocessable();
});

it('blocks a slot only for the party size that table fits', function () {
    Reservation::factory()->at('2030-03-06 19:00')->create(['dining_table_id' => $this->twoTop->id]);

    $available = fn (int $party) => collect(slotsFor('2030-03-06', $party))->firstWhere('time', '19:00')['available'];

    expect($available(2))->toBeFalse()
        ->and($available(4))->toBeTrue();
});

it('blocks overlapping times but frees the table afterwards', function () {
    Reservation::factory()->at('2030-03-06 19:00')->create(['dining_table_id' => $this->twoTop->id]);
    $at = fn (string $time) => collect(slotsFor('2030-03-06', 2))->firstWhere('time', $time)['available'];

    expect($at('17:30'))->toBeFalse()
        ->and($at('20:30'))->toBeFalse()
        ->and($at('20:45'))->toBeTrue()
        ->and($at('12:00'))->toBeTrue();
});

it('ignores cancelled bookings', function () {
    Reservation::factory()->at('2030-03-06 19:00')->status(ReservationStatus::Cancelled)
        ->create(['dining_table_id' => $this->twoTop->id]);

    expect(collect(slotsFor('2030-03-06', 2))->firstWhere('time', '19:00')['available'])->toBeTrue();
});

it('confirms small parties with no deposit', function () {
    $response = $this->post('/book', bookingPayload());

    $reservation = Reservation::query()->firstOrFail();
    $response->assertRedirect("/book/{$reservation->reference}");

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->deposit)->toBe(0)
        ->and($reservation->deposit_status)->toBeNull()
        ->and($reservation->dining_table_id)->toBe($this->twoTop->id)
        ->and($reservation->phone)->toBe('+447700900123')
        ->and($reservation->occasion)->toBe('Birthday')
        ->and($reservation->ends_at->diffInMinutes($reservation->starts_at, true))->toBe(105.0)
        ->and($reservation->reference)->toMatch('/^R[A-Z2-9]{5}$/');
});

it('holds large parties as pending deposit', function () {
    $this->post('/book', bookingPayload(['party_size' => 6]))->assertRedirect();

    $reservation = Reservation::query()->firstOrFail();

    expect($reservation->status)->toBe(ReservationStatus::PendingDeposit)
        ->and($reservation->deposit)->toBe(6000)
        ->and($reservation->deposit_status->value)->toBe('pending')
        ->and($reservation->dining_table_id)->toBe($this->sixTop->id);
});

it('links the booking to the signed-in customer', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/book', bookingPayload())->assertRedirect();

    expect(Reservation::query()->firstOrFail()->user_id)->toBe($user->id);
});

it('does not double book when the last table is taken', function () {
    $this->post('/book', bookingPayload())->assertRedirect();

    $this->post('/book', bookingPayload(['email' => 'other@example.com']))
        ->assertSessionHasErrors('time');

    expect(Reservation::query()->count())->toBe(1);
});

it('re-checks under the lock if the table goes between validation and saving', function () {
    $store = new ReflectionMethod(ReservationController::class, 'store');
    expect($store->getNumberOfParameters())->toBe(1);

    // Another booking lands first for the only table of that size.
    Reservation::factory()->at('2030-03-06 19:00')->create(['dining_table_id' => $this->twoTop->id]);

    expect(app(TableAvailability::class)->findTable(CarbonImmutable::parse('2030-03-06 19:00', 'Europe/London'), 2, lock: true))
        ->toBeNull();
});

it('rejects a party above the maximum', function () {
    $this->post('/book', bookingPayload(['party_size' => 11]))->assertSessionHasErrors('party_size');
});

it('validates the details', function () {
    $this->post('/book', bookingPayload([
        'name' => '',
        'email' => 'nope',
        'phone' => '020 7946 0321',
        'occasion' => 'party',
        'notes' => str_repeat('a', 501),
    ]))->assertSessionHasErrors(['name', 'email', 'phone', 'occasion', 'notes']);

    expect(Reservation::query()->count())->toBe(0);
});

it('rejects closed days, past times and times outside service', function () {
    $this->post('/book', bookingPayload(['date' => '2030-03-04']))->assertSessionHasErrors('time');
    $this->post('/book', bookingPayload(['date' => '2030-03-06', 'time' => '16:00']))->assertSessionHasErrors('time');
    $this->post('/book', bookingPayload(['date' => '2030-03-06', 'time' => '19:10']))->assertSessionHasErrors('time');
    $this->post('/book', bookingPayload(['date' => '2030-05-30']))->assertSessionHasErrors('time');
    $this->post('/book', bookingPayload(['date' => '2030-03-04', 'time' => '12:00']))->assertSessionHasErrors('time');
});

it('lets a guest view their own confirmation but nobody else', function () {
    $this->post('/book', bookingPayload())->assertRedirect();
    $reference = Reservation::query()->firstOrFail()->reference;

    $this->get("/book/{$reference}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('book/show')
            ->where('reservation.reference', $reference)
            ->where('reservation.time', '19:00')
            ->where('reservation.status', 'confirmed'));

    $this->flushSession();
    $this->get("/book/{$reference}")->assertForbidden();
});

it('lets the customer and staff view a booking, and not another customer', function () {
    $owner = User::factory()->create();
    $reservation = Reservation::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner)->get("/book/{$reservation->reference}")->assertOk();
    $this->actingAs(User::factory()->create())->get("/book/{$reservation->reference}")->assertForbidden();
    $this->actingAs(User::factory()->role(Role::Waiter)->create())->get("/book/{$reservation->reference}")->assertOk();
});
