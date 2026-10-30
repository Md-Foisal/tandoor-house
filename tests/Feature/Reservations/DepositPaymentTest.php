<?php

use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Models\User;
use App\Policies\ReservationPolicy;
use App\Support\DepositHold;
use App\Support\GuestLinks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->withoutVite();
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->travelTo(CarbonImmutable::parse('2030-03-04 10:00', 'Europe/London'));
});

function pendingBooking(array $overrides = []): Reservation
{
    return Reservation::factory()->pendingDeposit()->at('2030-03-06 19:00')->create($overrides);
}

function asBooker(Reservation $reservation): void
{
    test()->withSession([ReservationPolicy::SESSION_KEY => [$reservation->reference]]);
}

function confirmDeposit(Reservation $reservation, ?string $token = 'tok_visa'): TestResponse
{
    $payment = $reservation->payments()->latest('id')->firstOrFail();

    return test()->postJson(route('book.pay.confirm', $reservation), ['reference' => $payment->reference, 'token' => $token]);
}

it('pays a deposit and confirms the table', function () {
    Event::fake([ReservationUpdated::class]);
    $reservation = pendingBooking();
    asBooker($reservation);

    $this->get(route('book.pay', $reservation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('book/pay')
            ->where('payment.amount', 6000)
            ->where('hold.expired', false)
            ->where('payment.confirmUrl', route('book.pay.confirm', $reservation)));

    confirmDeposit($reservation)->assertOk()
        ->assertJson(['status' => 'paid', 'redirect' => route('book.show', $reservation)]);

    $reservation->refresh();
    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->deposit_status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(ReservationUpdated::class, 1);
});

it('confirms only once when confirmed twice', function () {
    Event::fake([ReservationUpdated::class]);
    $reservation = pendingBooking();
    asBooker($reservation);
    $this->get(route('book.pay', $reservation))->assertOk();

    confirmDeposit($reservation)->assertJson(['status' => 'paid']);
    confirmDeposit($reservation)->assertJson(['status' => 'paid']);

    expect($reservation->payments()->count())->toBe(1);
    Event::assertDispatchedTimes(ReservationUpdated::class, 1);
});

it('declines a card with a friendly message and lets the guest retry', function () {
    $reservation = pendingBooking(['deposit' => 5151]);
    asBooker($reservation);
    $this->get(route('book.pay', $reservation))->assertOk();

    confirmDeposit($reservation)->assertOk()
        ->assertJson(['status' => 'failed'])
        ->assertJsonPath('message', fn ($message) => is_string($message) && $message !== '');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::PendingDeposit)
        ->and($reservation->deposit_status)->toBe(PaymentStatus::Failed);

    $this->get(route('book.pay', $reservation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('declined', __('payments.declined')));

    expect($reservation->payments()->count())->toBe(2);
    confirmDeposit($reservation)->assertJson(['status' => 'failed']);
    expect($reservation->refresh()->status)->toBe(ReservationStatus::PendingDeposit);
});

it('lets a declined test card be tried again', function () {
    $reservation = pendingBooking();
    asBooker($reservation);
    $this->get(route('book.pay', $reservation))->assertOk();

    confirmDeposit($reservation, 'tok_decline')->assertJson(['status' => 'failed']);
    expect($reservation->refresh()->status)->toBe(ReservationStatus::PendingDeposit);

    confirmDeposit($reservation, 'tok_visa')->assertJson(['status' => 'paid']);
    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('keeps other people out of someone else\'s deposit', function () {
    $reservation = pendingBooking(['user_id' => User::factory()->create()->id]);

    $this->get(route('book.pay', $reservation))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('book.pay', $reservation))->assertForbidden();
    $this->postJson(route('book.pay.confirm', $reservation), ['reference' => 'fake_x'])->assertForbidden();
});

it('lets the signed-in booker pay', function () {
    $user = User::factory()->create();
    $reservation = pendingBooking(['user_id' => $user->id]);

    $this->actingAs($user)->get(route('book.pay', $reservation))->assertOk();
});

it('sends a paid booking straight back to the booking page', function () {
    $reservation = Reservation::factory()->forParty(6)->at('2030-03-06 19:00')->create();
    asBooker($reservation);

    $this->get(route('book.pay', $reservation))->assertRedirect(route('book.show', $reservation));
});

it('shows an expired hold instead of a payment form', function () {
    $reservation = pendingBooking();
    asBooker($reservation);
    $this->travel(16)->minutes();

    expect(app(DepositHold::class)->expired($reservation))->toBeTrue();

    $this->get(route('book.pay', $reservation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('hold.expired', true)->where('payment', null));
    expect($reservation->payments()->count())->toBe(0);
});

it('lists the pay link and hold on the booking page', function () {
    $reservation = pendingBooking();
    asBooker($reservation);

    $this->get(route('book.show', $reservation))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('pay.url', route('book.pay', $reservation))->where('pay.expired', false));
});

it('opens a booking from a signed link in a fresh session', function () {
    $reservation = Reservation::factory()->at('2030-03-06 19:00')->create();

    $this->get(route('book.show', $reservation))->assertForbidden();

    $this->flushSession()->get(GuestLinks::booking($reservation))->assertOk()
        ->assertInertia(fn ($page) => $page->component('book/show')->where('reservation.reference', $reservation->reference));

    $this->get(route('book.show', $reservation))->assertOk();
});

it('refuses a tampered signed link', function () {
    $reservation = Reservation::factory()->at('2030-03-06 19:00')->create();
    $other = Reservation::factory()->at('2030-03-06 20:00')->create();

    $this->get(str_replace($reservation->reference, $other->reference, GuestLinks::booking($reservation)))->assertForbidden();
    $this->get(GuestLinks::booking($reservation).'0')->assertForbidden();
});

it('refuses an expired signed link', function () {
    $reservation = Reservation::factory()->at('2030-03-06 19:00')->create();
    $link = GuestLinks::booking($reservation);

    $this->travelTo(Carbon::parse('2030-03-06 19:00')->addDays(9));

    $this->get($link)->assertForbidden();
});
