<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\AttemptStatus;
use App\Payments\Exceptions\GatewayNotConfigured;
use App\Payments\Payable;
use App\Payments\PaymentAttempt;
use App\Payments\PaymentGateway;
use App\Payments\RefundResult;
use App\Payments\WebhookOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Behaves like Stripe without the network: a started payment waits for the
 * test form (or settles at once with `confirm`), and any amount ending in
 * the configured pence (51p) is declined so failure paths can be exercised.
 */
class FakeGateway implements PaymentGateway
{
    public function __construct()
    {
        if (app()->isProduction()) {
            throw new GatewayNotConfigured('The fake payment gateway cannot run in production.');
        }
    }

    public function name(): string
    {
        return 'fake';
    }

    public function start(Payable $for, int $amount, PaymentMethod $method, array $context = []): PaymentAttempt
    {
        $reference = 'fake_'.substr(hash('sha256', implode('|', [
            $for instanceof Model ? $for->getMorphClass().':'.$for->getKey() : $for->paymentReference(),
            $amount,
            $method->value,
            $context['attempt'] ?? 1,
        ])), 0, 24);

        if (($context['confirm'] ?? false) === true) {
            return $this->decide($reference, $amount);
        }

        return new PaymentAttempt(AttemptStatus::RequiresAction, $reference, $reference.'_secret_test');
    }

    public function retrieve(Payment $payment): PaymentAttempt
    {
        if (in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)) {
            return new PaymentAttempt(AttemptStatus::Succeeded, (string) $payment->reference);
        }

        return $this->decide((string) $payment->reference, $payment->amount);
    }

    public function refund(Payment $payment, int $amount, string $reason): RefundResult
    {
        $count = count($payment->meta['refunds'] ?? []) + 1;

        return new RefundResult(true, 'fake_re_'.substr(hash('sha256', $payment->reference.'|'.$count.'|'.$amount), 0, 20));
    }

    public function handleWebhook(Request $request): ?WebhookOutcome
    {
        return null;
    }

    private function decide(string $reference, int $amount): PaymentAttempt
    {
        if ($amount % 100 === (int) config('payments.fake.decline_pence', 51)) {
            return new PaymentAttempt(AttemptStatus::Failed, $reference, message: __('payments.fake.declined'));
        }

        return new PaymentAttempt(AttemptStatus::Succeeded, $reference);
    }
}
