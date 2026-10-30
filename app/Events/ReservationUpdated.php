<?php

namespace App\Events;

use App\Broadcasting\Channels;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Carries only the id, status, table and date: the guest's name, email and
 * phone stay on the server and screens reload them through Inertia.
 */
class ReservationUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $reservationId;

    public readonly ReservationStatus $status;

    public readonly ?int $tableId;

    public readonly string $date;

    public function __construct(Reservation $reservation)
    {
        $this->reservationId = $reservation->id;
        $this->status = $reservation->status;
        $this->tableId = $reservation->dining_table_id;
        $this->date = $reservation->starts_at->toDateString();
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [Channels::staffReservations(), Channels::staffFloor()];
    }

    public function broadcastAs(): string
    {
        return 'reservation.updated';
    }

    /**
     * @return array{id: int, status: string, table_id: int|null, date: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->reservationId,
            'status' => $this->status->value,
            'table_id' => $this->tableId,
            'date' => $this->date,
        ];
    }
}
