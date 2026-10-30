<?php

namespace App\Events;

use App\Broadcasting\Channels;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Holds scalars rather than the model so the queued broadcast never
 * re-reads the order and the payload cannot leak contact details.
 */
class OrderPlaced implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $orderId;

    public readonly string $number;

    public readonly OrderChannel $orderChannel;

    public function __construct(Order $order)
    {
        $this->orderId = $order->id;
        $this->number = $order->number;
        $this->orderChannel = $order->channel;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [Channels::staffOrders(), Channels::staffKitchen()];

        if ($this->orderChannel === OrderChannel::DineIn) {
            $channels[] = Channels::staffFloor();
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    /**
     * @return array{id: int, number: string, channel: string, status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'number' => $this->number,
            'channel' => $this->orderChannel->value,
            'status' => OrderStatus::Placed->value,
        ];
    }
}
