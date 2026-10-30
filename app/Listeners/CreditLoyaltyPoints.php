<?php

namespace App\Listeners;

use App\Actions\Loyalty\CreditOrderPoints;
use App\Actions\Loyalty\ReturnRedeemedPoints;
use App\Actions\Loyalty\ReverseOrderPoints;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Events\PaymentUpdated;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Keeps the points ledger in step with orders: credit on completion, take
 * back after a full refund, hand a spent reward back when an order is
 * cancelled or rejected.
 */
class CreditLoyaltyPoints implements ShouldQueue
{
    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        $order = Order::find($event->orderId);

        if ($order === null) {
            return;
        }

        match ($event->to) {
            OrderStatus::Completed => app(CreditOrderPoints::class)->handle($order),
            OrderStatus::Cancelled, OrderStatus::Rejected => app(ReturnRedeemedPoints::class)->handle($order),
            default => null,
        };
    }

    public function handlePaymentUpdated(PaymentUpdated $event): void
    {
        if ($event->payment->status !== PaymentStatus::Refunded) {
            return;
        }

        $order = $event->payment->payableModel();

        if ($order instanceof Order) {
            app(ReverseOrderPoints::class)->handle($order);
        }
    }
}
