<?php

namespace App\Events;

use App\Broadcasting\Channels;
use App\Enums\LineStatus;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class KitchenLineUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $lineId;

    public readonly LineStatus $status;

    public readonly int $orderId;

    public readonly string $number;

    public function __construct(OrderLine $line, Order $order)
    {
        $this->lineId = $line->id;
        $this->status = $line->status;
        $this->orderId = $order->id;
        $this->number = $order->number;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            Channels::staffKitchen(),
            Channels::staffOrders(),
            Channels::order($this->number),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kitchen.line';
    }

    /**
     * @return array{id: int, status: string, order_id: int, number: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->lineId,
            'status' => $this->status->value,
            'order_id' => $this->orderId,
            'number' => $this->number,
        ];
    }
}
