<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\TableStatus;
use App\Events\ReservationUpdated;
use App\Listeners\NotifyCustomerOfBooking;
use App\Models\ActivityLog;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Notifications\Customer\BookingNotice;
use App\Notifications\Customer\BookingReminder;
use App\Payments\AttemptStatus;
use App\Payments\Payments;
use App\Payments\WebhookOutcome;
use App\Support\Automation;
use App\Support\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

function switchOff(string $key): void
{
    $automations = (array) app(Settings::class)->get('automations', []);
    app(Settings::class)->set('automations', [...$automations, $key => false]);
}

function bookingIn(int $minutes, array $state = []): Reservation
{
    $start = now()->addMinutes($minutes);

    return Reservation::factory()->create([
        'starts_at' => $start,
        'ends_at' => $start->copy()->addMinutes(105),
        'created_at' => now()->subDay(),
        ...$state,
    ]);
}

it('reminds a confirmed booking once, inside two hours', function () {
    Notification::fake();
    $soon = bookingIn(90);
    $later = bookingIn(240);

    $this->artisan('bookings:remind')->assertSuccessful();
    $this->artisan('bookings:remind')->assertSuccessful();

    Notification::assertSentOnDemandTimes(BookingReminder::class, 1);
    expect($soon->fresh()->reminded_at)->not->toBeNull()
        ->and($later->fresh()->reminded_at)->toBeNull()
        ->and(ActivityLog::where('event', 'automation.booking.reminded')->count())->toBe(1);
});

it('skips reminders for cancelled bookings and bookings made inside the window', function () {
    Notification::fake();
    bookingIn(60, ['status' => ReservationStatus::Cancelled]);
    bookingIn(60, ['created_at' => now()->subMinutes(10)]);

    $this->artisan('bookings:remind');

    Notification::assertNothingSent();
});

it('sends nothing when reminders are switched off', function () {
    Notification::fake();
    switchOff(Automation::RESERVATION_REMINDERS);
    $booking = bookingIn(60);

    $this->artisan('bookings:remind');

    Notification::assertNothingSent();
    expect($booking->fresh()->reminded_at)->toBeNull();
});

it('releases a table once the no-show grace has passed, keeping the deposit', function () {
    Event::fake([ReservationUpdated::class]);
    $table = DiningTable::factory()->create(['status' => TableStatus::Reserved]);
    $late = bookingIn(-25, ['dining_table_id' => $table->id, 'deposit' => 6000]);
    $recent = bookingIn(-10);
    $seated = bookingIn(-40, ['status' => ReservationStatus::Seated]);

    $this->artisan('bookings:release-no-shows')->assertSuccessful();
    $this->artisan('bookings:release-no-shows')->assertSuccessful();

    expect($late->fresh()->status)->toBe(ReservationStatus::NoShow)
        ->and($late->fresh()->deposit)->toBe(6000)
        ->and($table->fresh()->status)->toBe(TableStatus::Free)
        ->and($recent->fresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($seated->fresh()->status)->toBe(ReservationStatus::Seated)
        ->and(ActivityLog::where('event', 'automation.booking.no_show')->count())->toBe(1);
    Event::assertDispatchedTimes(ReservationUpdated::class, 1);
});

it('leaves late guests alone when no-show release is off', function () {
    switchOff(Automation::NO_SHOW_RELEASE);
    $late = bookingIn(-60);

    $this->artisan('bookings:release-no-shows');

    expect($late->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('cancels bookings whose deposit hold has lapsed', function () {
    Event::fake([ReservationUpdated::class]);
    $table = DiningTable::factory()->create(['status' => TableStatus::Reserved]);
    $lapsed = Reservation::factory()->pendingDeposit()->create(['created_at' => now()->subMinutes(30), 'dining_table_id' => $table->id]);
    $holding = Reservation::factory()->pendingDeposit()->create(['created_at' => now()->subMinutes(5)]);

    $this->artisan('bookings:close-lapsed-holds')->assertSuccessful();
    $this->artisan('bookings:close-lapsed-holds')->assertSuccessful();

    expect($lapsed->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($table->fresh()->status)->toBe(TableStatus::Free)
        ->and($holding->fresh()->status)->toBe(ReservationStatus::PendingDeposit)
        ->and(ActivityLog::where('event', 'automation.booking.hold_lapsed')->count())->toBe(1);
    Event::assertDispatchedTimes(ReservationUpdated::class, 1);
});

it('leaves a booking whose deposit has landed for confirmation', function () {
    $paid = Reservation::factory()->pendingDeposit()->create(['created_at' => now()->subMinutes(30), 'deposit_status' => PaymentStatus::Paid]);

    $this->artisan('bookings:close-lapsed-holds')->assertSuccessful();

    expect($paid->fresh()->status)->toBe(ReservationStatus::PendingDeposit);
});

it('closes the open payment and refunds a deposit that lands after the hold was closed', function () {
    $reservation = Reservation::factory()->pendingDeposit(8)->create(['created_at' => now()->subMinutes(30)]);
    $payment = app(Payments::class)->start($reservation, PaymentMethod::Card)->payment;
    $reservation->forceFill(['created_at' => now()->subMinutes(30)])->save();

    $this->artisan('bookings:close-lapsed-holds')->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled)
        ->and($payment->fresh()->meta['cancelled'])->toBeTrue();

    $payments = app(Payments::class);
    $payments->applyWebhook($payments->gateway($payment->gateway), new WebhookOutcome((string) $payment->reference, AttemptStatus::Succeeded, $payment->amount));

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
});

it('closes every lapsed hold even when there are more than one chunk', function () {
    Reservation::factory()->pendingDeposit()->count(105)->create(['created_at' => now()->subMinutes(30)]);

    $this->artisan('bookings:close-lapsed-holds')->assertSuccessful();

    expect(Reservation::query()->where('status', ReservationStatus::PendingDeposit)->count())->toBe(0);
});

it('releases every late booking even when there are more than one chunk', function () {
    foreach (range(1, 105) as $i) {
        bookingIn(-30);
    }

    $this->artisan('bookings:release-no-shows')->assertSuccessful();

    expect(Reservation::query()->where('status', ReservationStatus::Confirmed)->count())->toBe(0);
});

it('ignores bookings that started more than six hours ago', function () {
    $old = bookingIn(-60 * 7);

    $this->artisan('bookings:release-no-shows')->assertSuccessful();

    expect($old->fresh()->status)->toBe(ReservationStatus::Confirmed);
});

it('logs a sent booking confirmation without personal data', function () {
    Notification::fake();
    $booking = Reservation::factory()->create();

    app(NotifyCustomerOfBooking::class)->handle(new ReservationUpdated($booking));

    Notification::assertSentOnDemand(BookingNotice::class);
    $log = ActivityLog::where('event', 'automation.notification.sent')->sole();
    expect($log->properties['kind'])->toBe('booking.confirmed')
        ->and(json_encode($log->properties))->not->toContain($booking->email);
});

it('stays quiet when notifications are switched off', function () {
    Notification::fake();
    switchOff(Automation::NOTIFICATIONS);

    app(NotifyCustomerOfBooking::class)->handle(new ReservationUpdated(Reservation::factory()->create()));

    Notification::assertNothingSent();
    expect(ActivityLog::where('event', 'automation.notification.sent')->count())->toBe(0);
});
