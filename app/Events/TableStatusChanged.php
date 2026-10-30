<?php

namespace App\Events;

use App\Broadcasting\Channels;
use App\Enums\TableStatus;
use App\Models\DiningTable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when staff seat, clear or otherwise move a table, so every floor screen agrees. */
class TableStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $tableId;

    public function __construct(DiningTable $table, public readonly TableStatus $from, public readonly TableStatus $to)
    {
        $this->tableId = $table->id;
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [Channels::staffFloor()];
    }

    public function broadcastAs(): string
    {
        return 'table.status';
    }

    /**
     * @return array{id: int, from: string, to: string}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->tableId, 'from' => $this->from->value, 'to' => $this->to->value];
    }
}
