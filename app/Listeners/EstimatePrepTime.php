<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\KitchenLineUpdated;
use App\Events\OrderStatusChanged;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\PrepTimeEstimator;
use App\Support\Automation;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Sets the ready-by time when an order is accepted and corrects it each
 * time the kitchen bumps a line.
 */
class EstimatePrepTime implements ShouldQueue
{
    private const FALLBACK_PREP_MINUTES = 15;

    public function __construct(
        private readonly PrepTimeEstimator $estimator,
        private readonly Automation $automation,
    ) {}

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        if ($event->to === OrderStatus::Accepted) {
            $this->refresh($event->orderId, announce: true);
        }
    }

    public function handleKitchenLineUpdated(KitchenLineUpdated $event): void
    {
        $this->refresh($event->orderId, announce: false);
    }

    private function refresh(int $orderId, bool $announce): void
    {
        $order = Order::query()->with(['lines', 'deliveryZone'])->find($orderId);

        if ($order === null) {
            return;
        }

        if (! $this->automation->enabled(Automation::PREP_ESTIMATES)) {
            if ($announce && $order->estimated_ready_at === null) {
                $minutes = (int) ($order->lines->max(fn (OrderLine $line) => $line->menuItem->prep_minutes ?? 0) ?: self::FALLBACK_PREP_MINUTES);
                $order->forceFill(['estimated_ready_at' => now()->addMinutes($minutes)])->save();
            }

            return;
        }

        $this->estimator->refresh($order);

        // A retried job must not write the entry twice.
        if ($announce && $order->estimated_ready_at !== null && ! $this->alreadyLogged($order)) {
            $this->automation->log(
                'automation.order.estimated',
                __('automations.orders.estimated', ['number' => $order->number, 'time' => $order->estimated_ready_at->timezone('Europe/London')->format('H:i')]),
                $order,
            );
        }
    }

    private function alreadyLogged(Order $order): bool
    {
        return ActivityLog::query()
            ->where('event', 'automation.order.estimated')
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->exists();
    }
}
