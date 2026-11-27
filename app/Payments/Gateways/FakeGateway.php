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
use Illuminate\Support\Facades\Cache;

/**
 * Behaves like Stripe without the network: a started payment waits for the
 * test form (or settles at once with `confirm`), and any amount ending in
 * the configured pence (51p) is declined so failure paths can be exercised.
 */
class FakeGateway implements PaymentGateway
{
    public function __construct()
    {
        if (app()->isProduction() && ! config('demo.enabled')) {
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

        if (Cache::has($this->cancelledKey((string) $payment->reference))) {
            return new PaymentAttempt(AttemptStatus::Failed, (string) $payment->reference, message: __('payments.cancelled'));
        }

        return $this->decide((string) $payment->reference, $payment->amount);
    }

    public function cancel(Payment $payment): void
    {
        if ($payment->reference !== null) {
            Cache::put($this->cancelledKey($payment->reference), true, now()->addDay());
        }
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

    private function cancelledKey(string $reference): string
    {
        return 'payments.fake.cancelled.'.$reference;
    }

    private function decide(string $reference, int $amount): PaymentAttempt
    {
        if ($amount % 100 === (int) config('payments.fake.decline_pence', 51)) {
            return new PaymentAttempt(AttemptStatus::Failed, $reference, message: __('payments.fake.declined'));
        }

        return new PaymentAttempt(AttemptStatus::Succeeded, $reference);
    }
}
