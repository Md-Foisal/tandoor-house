<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\Payments;
use App\Services\OrderStateMachine;
use App\Support\Automation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('orders:expire-unpaid')]
#[Description('Cancel card orders that were never paid for')]
class ExpireUnpaidOrders extends Command
{
    private const GRACE_MINUTES = 30;

    public function handle(Payments $payments, OrderStateMachine $machine, Automation $automation): int
    {
        $cancelled = 0;

        Order::query()
            ->where('status', OrderStatus::Placed)
            ->whereIn('payment_method', [PaymentMethod::Card, PaymentMethod::ApplePay, PaymentMethod::GooglePay])
            ->where('payment_status', '!=', PaymentStatus::Paid)
            ->where('placed_at', '<=', now()->subMinutes(self::GRACE_MINUTES))
            ->each(function (Order $order) use ($payments, $machine, $automation, &$cancelled) {
                $payments->cancelOpen($order);

                // A payment may have landed while the attempts were being closed.
                $order->refresh();

                if ($order->status !== OrderStatus::Placed || $order->payment_status === PaymentStatus::Paid) {
                    return;
                }

                try {
                    $machine->transition($order, OrderStatus::Cancelled, null, __('automations.orders.expired_reason'));
                } catch (ValidationException) {
                    return;
                }

                $cancelled++;
                $automation->log('automation.order.expired_unpaid', __('automations.orders.expired', ['number' => $order->number]), $order);
            });

        $this->components->info("Cancelled {$cancelled} unpaid orders.");

        return self::SUCCESS;
    }
}
