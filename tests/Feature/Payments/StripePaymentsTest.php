<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Events\PaymentUpdated;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\AttemptStatus;
use App\Payments\Payments;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config([
        'payments.methods.card' => 'stripe',
        'payments.stripe.secret' => 'sk_test_123',
        'payments.stripe.webhook_secret' => 'whsec_test',
    ]);
});

function stripeOrder(int $total = 4200): Order
{
    return Order::factory()->create(['total' => $total, 'payment_method' => PaymentMethod::Card, 'payment_status' => PaymentStatus::Pending]);
}

/**
 * @param  array<string, mixed>  $event
 */
function postStripeWebhook(array $event, string $secret = 'whsec_test', ?int $at = null): TestResponse
{
    $body = json_encode($event, JSON_THROW_ON_ERROR);
    $at ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', $at.'.'.$body, $secret);

    return test()->call('POST', '/webhooks/payments/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$at},v1={$signature}",
    ], $body);
}

/**
 * @return array<string, mixed>
 */
function intentEvent(string $type, string $id, int $amount, string $eventId = 'evt_1'): array
{
    return [
        'id' => $eventId,
        'type' => $type,
        'data' => ['object' => ['id' => $id, 'object' => 'payment_intent', 'amount' => $amount, 'amount_received' => $amount, 'currency' => 'gbp', 'status' => 'succeeded']],
    ];
}

it('creates a payment intent with automatic payment methods', function () {
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::response([
            'id' => 'pi_123', 'status' => 'requires_payment_method', 'client_secret' => 'pi_123_secret_abc', 'amount' => 4200,
        ]),
    ]);
    $order = stripeOrder();

    $started = app(Payments::class)->start($order, PaymentMethod::Card, ['email' => 'guest@example.com']);

    expect($started->attempt->status)->toBe(AttemptStatus::RequiresAction)
        ->and($started->attempt->clientSecret)->toBe('pi_123_secret_abc')
        ->and($started->payment->gateway)->toBe('stripe')
        ->and($started->payment->reference)->toBe('pi_123')
        ->and($started->payment->status)->toBe(PaymentStatus::Pending);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/payment_intents'
        && $request->hasHeader('Authorization', 'Bearer sk_test_123')
        && $request->hasHeader('Idempotency-Key')
        && $request['amount'] === 4200
        && $request['currency'] === 'gbp'
        && $request['automatic_payment_methods']['enabled'] === 'true'
        && $request['metadata']['reference'] === $order->number
        && $request['receipt_email'] === 'guest@example.com');
});

it('confirms a returning customer by asking stripe', function () {
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::response(['id' => 'pi_9', 'status' => 'requires_payment_method', 'client_secret' => 's']),
        'api.stripe.com/v1/payment_intents/pi_9' => Http::response(['id' => 'pi_9', 'status' => 'succeeded']),
    ]);
    $order = stripeOrder();
    app(Payments::class)->start($order, PaymentMethod::Card);

    expect(app(Payments::class)->confirm('pi_9')->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('refunds through the stripe refunds api', function () {
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);
    $order = stripeOrder(4200);
    $payment = Payment::factory()->forOrder($order)->create(['reference' => 'pi_paid', 'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id]);

    app(Payments::class)->refund($payment, 1200, 'Missing raita', User::factory()->role(Role::Manager)->create());

    expect($payment->refunded)->toBe(1200)
        ->and($payment->meta['refunds'][0]['reference'])->toBe('re_1');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/refunds'
        && $request['payment_intent'] === 'pi_paid'
        && $request['amount'] === 1200);
});

it('changes nothing when stripe refuses a refund', function () {
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['error' => ['message' => 'Charge already refunded']], 400)]);
    $payment = Payment::factory()->forOrder(stripeOrder())->create(['reference' => 'pi_x']);

    expect(fn () => app(Payments::class)->refund($payment, 100, 'x', User::factory()->role(Role::Owner)->create()))
        ->toThrow(ValidationException::class, 'Charge already refunded');

    expect($payment->fresh()->refunded)->toBe(0);
});

it('marks a payment paid from a signed webhook and ignores the replay', function () {
    Event::fake([PaymentUpdated::class]);
    $order = stripeOrder(4200);
    $payment = Payment::factory()->forOrder($order)->pending()->create(['reference' => 'pi_hook', 'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id]);
    $event = intentEvent('payment_intent.succeeded', 'pi_hook', 4200);

    postStripeWebhook($event)->assertNoContent();
    postStripeWebhook($event)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Event::assertDispatchedTimes(PaymentUpdated::class, 1);
});

it('does not let a late failure undo a payment', function () {
    $order = stripeOrder(4200);
    $payment = Payment::factory()->forOrder($order)->pending()->create(['reference' => 'pi_late']);

    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_late', 4200, 'evt_a'))->assertNoContent();
    postStripeWebhook(intentEvent('payment_intent.payment_failed', 'pi_late', 4200, 'evt_b'))->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

it('rejects a webhook with the wrong signature', function () {
    $payment = Payment::factory()->forOrder(stripeOrder())->pending()->create(['reference' => 'pi_bad']);

    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_bad', 4200), 'whsec_wrong')->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('rejects a correctly signed but stale webhook', function () {
    $payment = Payment::factory()->forOrder(stripeOrder())->pending()->create(['reference' => 'pi_old']);

    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_old', 4200), at: now()->subMinutes(10)->getTimestamp())->assertStatus(400);

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('ignores a webhook whose amount does not match', function () {
    $payment = Payment::factory()->forOrder(stripeOrder(4200))->pending()->create(['reference' => 'pi_amt']);

    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_amt', 100))->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('accepts webhooks for unknown payments and events without changing anything', function () {
    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_unknown', 100))->assertNoContent();
    postStripeWebhook(['id' => 'evt_2', 'type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]])->assertNoContent();

    expect(Payment::count())->toBe(0);
});

it('returns not found for an unknown driver', function () {
    $this->post('/webhooks/payments/paypal')->assertNotFound();
});

it('ignores webhooks sent to the fake driver', function () {
    $payment = Payment::factory()->forOrder(stripeOrder())->pending()->create(['gateway' => 'fake', 'reference' => 'fake_1']);

    $this->postJson('/webhooks/payments/fake', ['reference' => 'fake_1', 'status' => 'succeeded'])->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending);
});

it('cancels the declined intent at stripe before creating a new one', function () {
    Http::fake([
        'api.stripe.com/v1/payment_intents' => Http::sequence()
            ->push(['id' => 'pi_old', 'status' => 'requires_payment_method', 'client_secret' => 's1'])
            ->push(['id' => 'pi_new', 'status' => 'requires_payment_method', 'client_secret' => 's2']),
        'api.stripe.com/v1/payment_intents/pi_old/cancel' => Http::response(['id' => 'pi_old', 'status' => 'canceled']),
    ]);
    $order = stripeOrder();
    $payments = app(Payments::class);
    $old = $payments->start($order, PaymentMethod::Card)->payment;
    postStripeWebhook(intentEvent('payment_intent.payment_failed', 'pi_old', 4200))->assertNoContent();

    $new = $payments->start($order->refresh(), PaymentMethod::Card)->payment;

    expect($new->reference)->toBe('pi_new')
        ->and($old->refresh()->meta['cancelled'])->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/payment_intents/pi_old/cancel'
        && $request['cancellation_reason'] === 'abandoned');
});

it('treats an intent stripe can no longer cancel as cancelled here', function () {
    Http::fake(['api.stripe.com/v1/payment_intents/pi_done/cancel' => Http::response(
        ['error' => ['code' => 'payment_intent_unexpected_state', 'message' => 'already succeeded']], 400,
    )]);
    $order = stripeOrder();
    $payment = Payment::factory()->forOrder($order)->pending()->create(['reference' => 'pi_done', 'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id]);

    expect(app(Payments::class)->cancelOpen($order))->toBe(1)
        ->and($payment->refresh()->meta['cancelled'])->toBeTrue();
});

it('refunds through stripe when a cancelled intent succeeds anyway', function () {
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['id' => 're_auto', 'status' => 'succeeded'])]);
    $order = stripeOrder(4200);
    $order->forceFill(['status' => OrderStatus::Cancelled])->save();
    $payment = Payment::factory()->forOrder($order)->pending()->create(['reference' => 'pi_late_win', 'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id]);

    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_late_win', 4200))->assertNoContent();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($payment->meta['refunds'][0]['reference'])->toBe('re_auto');
    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.stripe.com/v1/refunds'
        && $request['payment_intent'] === 'pi_late_win' && $request['amount'] === 4200);
});

it('logs an automatic refund stripe refuses so staff can finish it', function () {
    Http::fake(['api.stripe.com/v1/refunds' => Http::response(['error' => ['message' => 'No']], 400)]);
    $order = stripeOrder(4200);
    $order->forceFill(['status' => OrderStatus::Cancelled])->save();
    $payment = Payment::factory()->forOrder($order)->pending()->create(['reference' => 'pi_stuck', 'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id]);

    postStripeWebhook(intentEvent('payment_intent.succeeded', 'pi_stuck', 4200))->assertNoContent();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->meta['auto_refund_failed']['amount'])->toBe(4200)
        ->and(ActivityLog::query()->where('event', 'payment.auto_refund_failed')->exists())->toBeTrue();
});

it('ignores a webhook in another currency', function () {
    $order = stripeOrder(4200);
    $payment = Payment::factory()->forOrder($order)->pending()->create(['reference' => 'pi_usd', 'payable_type' => $order->getMorphClass(), 'payable_id' => $order->id]);
    $event = intentEvent('payment_intent.succeeded', 'pi_usd', 4200);
    $event['data']['object']['currency'] = 'usd';

    postStripeWebhook($event)->assertNoContent();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});
