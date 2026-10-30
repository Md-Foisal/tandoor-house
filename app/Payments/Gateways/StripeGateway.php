<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\GatewayNotConfigured;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentException;
use App\Payments\Payable;
use App\Payments\PaymentAttempt;
use App\Payments\PaymentGateway;
use App\Payments\RefundResult;
use App\Payments\WebhookOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Stripe PaymentIntents over plain HTTP. Card, Apple Pay and Google Pay all
 * go through automatic payment methods: the browser confirms with the
 * client secret in Stripe Elements, so card details never reach us.
 */
class StripeGateway implements PaymentGateway
{
    private string $secret;

    public function __construct()
    {
        $secret = (string) config('payments.stripe.secret');

        if ($secret === '') {
            throw new GatewayNotConfigured('STRIPE_SECRET is not set.');
        }

        if (str_starts_with($secret, 'sk_live_') || str_starts_with($secret, 'rk_live_')) {
            throw new GatewayNotConfigured('Live Stripe keys are refused; use a test mode key.');
        }

        $this->secret = $secret;
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function start(Payable $for, int $amount, PaymentMethod $method, array $context = []): PaymentAttempt
    {
        $payload = [
            'amount' => $amount,
            'currency' => config('payments.currency', 'gbp'),
            'automatic_payment_methods' => ['enabled' => 'true'],
            'description' => $for->paymentDescription(),
            'metadata' => array_filter([
                'reference' => $for->paymentReference(),
                'payable_type' => $for instanceof Model ? $for->getMorphClass() : null,
                'payable_id' => $for instanceof Model ? (string) $for->getKey() : null,
            ]),
        ];

        if (is_string($context['email'] ?? null) && $context['email'] !== '') {
            $payload['receipt_email'] = $context['email'];
        }

        $response = $this->client($context['idempotency_key'] ?? null)->post('payment_intents', $payload);

        return $this->attempt($this->ok($response));
    }

    public function retrieve(Payment $payment): PaymentAttempt
    {
        return $this->attempt($this->ok($this->client()->get('payment_intents/'.rawurlencode((string) $payment->reference))));
    }

    public function refund(Payment $payment, int $amount, string $reason): RefundResult
    {
        $key = 'refund-'.$payment->id.'-'.count($payment->meta['refunds'] ?? []).'-'.$amount;

        $response = $this->client($key)->post('refunds', [
            'payment_intent' => $payment->reference,
            'amount' => $amount,
            'reason' => 'requested_by_customer',
            'metadata' => ['note' => mb_substr($reason, 0, 500)],
        ]);

        if ($response->failed()) {
            return new RefundResult(false, message: $this->errorMessage($response));
        }

        if (! in_array($response->json('status'), ['succeeded', 'pending'], true)) {
            return new RefundResult(false, message: $this->translated('payments.refund.gateway_failed'));
        }

        return new RefundResult(true, (string) $response->json('id'));
    }

    public function cancel(Payment $payment): void
    {
        if ($payment->reference === null || $payment->reference === '') {
            return;
        }

        $response = $this->client('cancel-'.$payment->reference)
            ->post('payment_intents/'.rawurlencode($payment->reference).'/cancel', ['cancellation_reason' => 'abandoned']);

        // Already cancelled or already succeeded: nothing left to stop.
        if ($response->successful() || $response->json('error.code') === 'payment_intent_unexpected_state') {
            return;
        }

        throw new PaymentException($this->errorMessage($response));
    }

    public function handleWebhook(Request $request): ?WebhookOutcome
    {
        $this->verify($request->getContent(), (string) $request->header('Stripe-Signature'));

        $event = json_decode($request->getContent(), true);

        if (! is_array($event)) {
            throw new InvalidWebhookSignature('Webhook body is not JSON.');
        }

        $status = match ($event['type'] ?? null) {
            'payment_intent.succeeded' => AttemptStatus::Succeeded,
            'payment_intent.payment_failed', 'payment_intent.canceled' => AttemptStatus::Failed,
            'payment_intent.processing' => AttemptStatus::Pending,
            default => null,
        };

        $intent = $event['data']['object'] ?? null;

        if ($status === null || ! is_array($intent) || ! is_string($intent['id'] ?? null)) {
            return null;
        }

        $currency = strtolower((string) config('payments.currency', 'gbp'));

        if (strtolower((string) ($intent['currency'] ?? '')) !== $currency) {
            Log::warning('Stripe webhook ignored: the payment is not in the configured currency.', [
                'intent' => $intent['id'],
                'currency' => $intent['currency'] ?? null,
                'expected' => $currency,
            ]);

            return null;
        }

        return new WebhookOutcome(
            $intent['id'],
            $status,
            $status === AttemptStatus::Succeeded ? (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0) : null,
            is_string($event['id'] ?? null) ? $event['id'] : null,
            $intent['last_payment_error']['message'] ?? null,
        );
    }

    /**
     * Stripe's documented scheme: HMAC-SHA256 of "{t}.{raw body}" with the
     * endpoint secret, compared with every v1 entry, within a tolerance so
     * an old captured request cannot be replayed later.
     */
    private function verify(string $payload, string $header): void
    {
        $secret = (string) config('payments.stripe.webhook_secret');

        if ($secret === '') {
            throw new InvalidWebhookSignature('STRIPE_WEBHOOK_SECRET is not set.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new InvalidWebhookSignature('Stripe-Signature header is missing or malformed.');
        }

        if (abs(now()->getTimestamp() - $timestamp) > (int) config('payments.stripe.tolerance', 300)) {
            throw new InvalidWebhookSignature('Stripe webhook timestamp is outside the tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new InvalidWebhookSignature('Stripe webhook signature does not match.');
    }

    private function client(?string $idempotencyKey = null): PendingRequest
    {
        $client = Http::baseUrl((string) config('payments.stripe.api'))
            ->withToken($this->secret)
            ->asForm()
            ->acceptJson()
            ->timeout(15);

        return $idempotencyKey === null ? $client : $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ok(Response $response): array
    {
        if ($response->failed()) {
            throw new PaymentException($this->errorMessage($response));
        }

        return (array) $response->json();
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function attempt(array $intent): PaymentAttempt
    {
        $status = match ($intent['status'] ?? null) {
            'succeeded' => AttemptStatus::Succeeded,
            'processing', 'requires_capture' => AttemptStatus::Pending,
            'canceled' => AttemptStatus::Failed,
            default => AttemptStatus::RequiresAction,
        };

        $error = $intent['last_payment_error'] ?? null;

        return new PaymentAttempt(
            $status,
            (string) ($intent['id'] ?? ''),
            is_string($intent['client_secret'] ?? null) ? $intent['client_secret'] : null,
            is_array($error) && is_string($error['message'] ?? null) ? $error['message'] : null,
        );
    }

    private function errorMessage(Response $response): string
    {
        $message = $response->json('error.message');

        return is_string($message) ? $message : $this->translated('payments.gateway_error');
    }

    private function translated(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
