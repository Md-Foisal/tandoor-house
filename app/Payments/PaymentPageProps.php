<?php

namespace App\Payments;

use App\Enums\PaymentMethod;

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
}
