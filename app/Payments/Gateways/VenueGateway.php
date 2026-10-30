<?php

namespace App\Payments\Gateways;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Payments\AttemptStatus;
use App\Payments\Payable;
use App\Payments\PaymentAttempt;
use App\Payments\PaymentGateway;
use App\Payments\RefundResult;
use App\Payments\WebhookOutcome;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pay at the venue, or cash to a rider: nothing to call. The payment stays
 * pending until staff take it (Payments::takeAtVenue), and a refund is cash
 * handed back.
 */
class VenueGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'venue';
    }

    public function start(Payable $for, int $amount, PaymentMethod $method, array $context = []): PaymentAttempt
    {
        return new PaymentAttempt(AttemptStatus::Pending, 'venue_'.Str::lower((string) Str::ulid()), message: __('payments.venue.pending'));
    }

    public function retrieve(Payment $payment): PaymentAttempt
    {
        $status = in_array($payment->status, [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded], true)
            ? AttemptStatus::Succeeded
            : AttemptStatus::Pending;

        return new PaymentAttempt($status, (string) $payment->reference);
    }

    public function refund(Payment $payment, int $amount, string $reason): RefundResult
    {
        return new RefundResult(true, 'cash_'.Str::lower((string) Str::ulid()), 'cash', __('payments.venue.cash_refund'));
    }

    public function handleWebhook(Request $request): ?WebhookOutcome
    {
        return null;
    }
}
