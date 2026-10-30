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

class OrderStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $orderId;

    public readonly string $number;

    public readonly OrderChannel $orderChannel;

    public readonly ?int $riderId;

    public function __construct(Order $order, public readonly ?OrderStatus $from, public readonly OrderStatus $to)
    {
        $this->orderId = $order->id;
        $this->number = $order->number;
        $this->orderChannel = $order->channel;
        $this->riderId = $order->rider_id;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_values(array_filter([
            Channels::staffOrders(),
            Channels::staffKitchen(),
            Channels::order($this->number),
            $this->orderChannel === OrderChannel::DineIn ? Channels::staffFloor() : null,
            $this->orderChannel === OrderChannel::Delivery && $this->riderId !== null ? Channels::rider($this->riderId) : null,
        ]));
    }

    public function broadcastAs(): string
    {
        return 'order.status';
    }

    /**
     * @return array{id: int, number: string, channel: string, from: string|null, to: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'number' => $this->number,
            'channel' => $this->orderChannel->value,
            'from' => $this->from?->value,
            'to' => $this->to->value,
        ];
    }
}
