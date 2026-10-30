<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\PaymentUpdated;
use App\Models\Order;

/**
 * Online-paid orders stay off the kitchen and staff boards until the money
 * lands; this is the moment they appear.
 */
class AnnounceOrderPaid
{
    public function handle(PaymentUpdated $event): void
    {
        $payment = $event->payment;

        if ($payment->status !== PaymentStatus::Paid || $payment->gateway === 'venue' || $event->from === PaymentStatus::Paid) {
            return;
        }

        $order = $payment->payableModel();

        if ($order instanceof Order && $order->status === OrderStatus::Placed) {
            OrderPlaced::dispatch($order);
        }
    }
}
