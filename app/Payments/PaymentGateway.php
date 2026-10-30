<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Payments\Exceptions\InvalidWebhookSignature;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /** The name stored in `payments.gateway` and used in the webhook URL. */
    public function name(): string;

    /**
     * @param  array<string, mixed>  $context  email, idempotency_key, attempt, confirm
     */
    public function start(Payable $for, int $amount, PaymentMethod $method, array $context = []): PaymentAttempt;

    /** Asks the gateway where a started payment stands now. */
    public function retrieve(Payment $payment): PaymentAttempt;

    public function refund(Payment $payment, int $amount, string $reason): RefundResult;

    /**
     * Null when the request is genuine but says nothing about a payment.
     *
     * @throws InvalidWebhookSignature
     */
    public function handleWebhook(Request $request): ?WebhookOutcome;
}
