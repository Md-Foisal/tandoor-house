<?php

namespace App\Events;

use App\Broadcasting\Channels;
use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a rider is put on a delivery or taken off it. The previous
 * rider's channel is included so their list drops the run.
 */
class RiderAssigned implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $orderId;

    public readonly string $number;

    public function __construct(Order $order, public readonly ?int $riderId, public readonly ?int $previousRiderId)
    {
        $this->orderId = $order->id;
        $this->number = $order->number;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $riders = array_unique(array_filter([$this->riderId, $this->previousRiderId]));

        return [
            Channels::staffOrders(),
            ...array_map(Channels::rider(...), array_values($riders)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rider.assigned';
    }

    /**
     * @return array{id: int, number: string, rider_id: int|null, previous_rider_id: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->orderId,
            'number' => $this->number,
            'rider_id' => $this->riderId,
            'previous_rider_id' => $this->previousRiderId,
        ];
    }
}
