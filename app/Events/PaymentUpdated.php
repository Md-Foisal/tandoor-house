<?php

namespace App\Events;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A payment was paid, failed, taken at the venue or refunded. The new state
 * is on the payment; `from` is what it was before.
 */
class PaymentUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Payment $payment,
        public PaymentStatus $from,
    ) {}
}
