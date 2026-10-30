<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\KitchenLineUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\PrepTimeEstimator;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sets the ready-by time when an order is accepted and corrects it each
 * time the kitchen bumps a line.
 */
class EstimatePrepTime implements ShouldQueue
{
    public function __construct(private readonly PrepTimeEstimator $estimator) {}

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        if ($event->to === OrderStatus::Accepted) {
            $this->refresh($event->orderId);
        }
    }

    public function handleKitchenLineUpdated(KitchenLineUpdated $event): void
    {
        $this->refresh($event->orderId);
    }

    private function refresh(int $orderId): void
    {
        $order = Order::query()->with(['lines', 'deliveryZone'])->find($orderId);

        if ($order !== null) {
            $this->estimator->refresh($order);
        }
    }
}
