<?php

namespace App\Payments;

use App\Models\Payment;

/**
 * The recorded row plus what the browser needs next (the client secret is
 * never stored).
 */
final readonly class StartedPayment
{
    public function __construct(
        public Payment $payment,
        public PaymentAttempt $attempt,
    ) {}
}
