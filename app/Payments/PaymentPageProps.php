<?php

namespace App\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;

/**
 * The props the shared front-end payment panel needs. The amount comes from
 * the recorded payment, never from the request.
 */
final class PaymentPageProps
{
    /** Methods taken through the browser (card, wallets) rather than at the venue. */
    public static function isOnline(PaymentMethod $method): bool
    {
        return in_array($method, [PaymentMethod::Card, PaymentMethod::ApplePay, PaymentMethod::GooglePay], true);
    }

    public static function driver(): string
    {
        return (string) config('payments.methods.card');
    }

    /**
     * @return array{clientSecret: string|null, driver: string, publishableKey: string|null, amount: int, reference: string, confirmUrl: string, returnUrl: string}
     */
    public static function for(StartedPayment $started, string $confirmUrl, string $returnUrl): array
    {
        $driver = $started->payment->gateway;

        return [
            'clientSecret' => $started->attempt->clientSecret,
            'driver' => $driver,
            'publishableKey' => $driver === 'stripe' ? (config('payments.stripe.key') ?: null) : null,
            'amount' => $started->payment->amount,
            'reference' => (string) $started->payment->reference,
            'confirmUrl' => $confirmUrl,
            'returnUrl' => $returnUrl,
        ];
    }

    /**
     * What the payment panel is told after a confirm: paid, failed or still
     * pending. Money sent straight back because it was no longer wanted
     * reads as failed, with the reason.
     *
     * @return array{0: 'paid'|'failed'|'pending', 1: string|null}
     */
    public static function outcome(Payment $payment): array
    {
        $why = $payment->meta['auto_refund'] ?? null;

        if (is_string($why) && $payment->status === PaymentStatus::Refunded) {
            return ['failed', (string) __("payments.auto_refund.customer.{$why}")];
        }

        return match ($payment->status) {
            PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded => ['paid', null],
            PaymentStatus::Failed => ['failed', is_string($payment->meta['failure'] ?? null) ? $payment->meta['failure'] : null],
            default => ['pending', null],
        };
    }
}
