<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Events\ReservationUpdated;
use App\Models\Reservation;
use App\Payments\AttemptStatus;
use App\Payments\Payments;
use App\Payments\WebhookOutcome;
use App\Policies\ReservationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    $this->withCookie(config('session.cookie'), Str::random(40));
    $this->travelTo(CarbonImmutable::parse('2030-03-04 10:00', 'Europe/London'));
});

function startedDeposit(): array
{
    $reservation = Reservation::factory()->pendingDeposit()->at('2030-03-06 19:00')->create();
    $started = app(Payments::class)->start($reservation, PaymentMethod::Card, ['email' => $reservation->email]);

    return [$reservation, $started->payment];
}

function webhookPaid(string $reference, int $amount): bool
{
    $payments = app(Payments::class);

    return $payments->applyWebhook($payments->gateway('fake'), new WebhookOutcome($reference, AttemptStatus::Succeeded, $amount));
}

it('confirms the booking when the webhook lands before the guest returns', function () {
    Event::fake([ReservationUpdated::class]);
    [$reservation, $payment] = startedDeposit();

    expect(webhookPaid($payment->reference, $payment->amount))->toBeTrue();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->deposit_status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(ReservationUpdated::class, 1);

    $this->withSession([ReservationPolicy::SESSION_KEY => [$reservation->reference]])
        ->postJson(route('book.pay.confirm', $reservation), ['reference' => $payment->reference, 'token' => 'tok_visa'])
        ->assertJson(['status' => 'paid']);

    Event::assertDispatchedTimes(ReservationUpdated::class, 1);
});

it('confirms once when the guest returns first and the webhook follows', function () {
    Event::fake([ReservationUpdated::class]);
    [$reservation, $payment] = startedDeposit();

    $this->withSession([ReservationPolicy::SESSION_KEY => [$reservation->reference]])
        ->postJson(route('book.pay.confirm', $reservation), ['reference' => $payment->reference, 'token' => 'tok_visa'])
        ->assertJson(['status' => 'paid']);

    expect(webhookPaid($payment->reference, $payment->amount))->toBeFalse();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
    Event::assertDispatchedTimes(ReservationUpdated::class, 1);
});

it('leaves a booking pending when the webhook says the payment failed', function () {
    Event::fake([ReservationUpdated::class]);
    [$reservation, $payment] = startedDeposit();

    app(Payments::class)->applyWebhook(app(Payments::class)->gateway('fake'), new WebhookOutcome($payment->reference, AttemptStatus::Failed));

    expect($reservation->refresh()->status)->toBe(ReservationStatus::PendingDeposit);
    Event::assertNotDispatched(ReservationUpdated::class);
});

it('does not revive a booking that was cancelled before the webhook', function () {
    Event::fake([ReservationUpdated::class]);
    [$reservation, $payment] = startedDeposit();
    $reservation->forceFill(['status' => ReservationStatus::Cancelled])->save();

    webhookPaid($payment->reference, $payment->amount);

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled);
    Event::assertNotDispatched(ReservationUpdated::class);
});
