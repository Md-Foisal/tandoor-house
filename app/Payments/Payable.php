<?php

namespace App\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;

/**
 * Something a customer pays for: an order, or a booking deposit. The model
 * owns the meaning of "paid" for itself; the Payments service decides when.
 */
interface Payable
{
    /** Pence the customer owes, computed on the server. */
    public function amountOwed(): int;

    public function paymentDescription(): string;

    /** The customer-facing reference, e.g. the order number. */
    public function paymentReference(): string;

    public function paymentStatus(): ?PaymentStatus;

    /**
     * @return HasOneOrMany<Payment, *, *>
     */
    public function payments(): HasOneOrMany;

    public function markPaid(): void;

    public function markPaymentFailed(): void;

    public function markRefunded(bool $fully): void;
}
