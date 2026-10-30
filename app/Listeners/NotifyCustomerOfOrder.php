<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Notifications\Customer\OrderUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotifyCustomerOfOrder implements ShouldQueue
{
    public function handleOrderPlaced(OrderPlaced $event): void
    {
        $this->notify($event->orderId, OrderStatus::Placed);
    }

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        // OrderPlaced already covers the first status.
        if ($event->to !== OrderStatus::Placed) {
            $this->notify($event->orderId, $event->to);
        }
    }

    private function notify(int $orderId, OrderStatus $stage): void
    {
        $order = Order::find($orderId);

        // The order may have moved on again before the queue got here.
        if ($order === null || ! OrderUpdate::shouldNotify($order, $stage)) {
            return;
        }

        $email = trim((string) ($order->contact['email'] ?? ''));
        $phone = trim((string) ($order->contact['phone'] ?? ''));

        if ($email === '' && $phone === '') {
            return;
        }

        // A retried job or a repeated event must not text the customer twice.
        if (! Cache::add("order-notice:{$order->id}:{$stage->value}", true, now()->addDay())) {
            return;
        }

        $route = Notification::route('sms', $phone)->route('whatsapp', $phone);

        if ($email !== '') {
            $route->route('mail', $email);
        }

        $route->notify(new OrderUpdate($order, $stage));
    }
}
