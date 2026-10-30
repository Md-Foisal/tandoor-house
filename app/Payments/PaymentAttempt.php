<?php

namespace App\Payments;

/**
 * What a gateway said about a payment: settled, waiting on the customer
 * (3-D Secure, the Stripe payment sheet, the fake test form), waiting on
 * staff (pay at the venue) or refused.
 */
final readonly class PaymentAttempt
{
    public function __construct(
        public AttemptStatus $status,
        public string $reference,
        public ?string $clientSecret = null,
        public ?string $message = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->status === AttemptStatus::Succeeded;
    }

    public function failed(): bool
    {
        return $this->status === AttemptStatus::Failed;
    }
}
