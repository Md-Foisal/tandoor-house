<?php

namespace App\Events;

use App\Broadcasting\Channels;
use App\Enums\TableRequestType;
use App\Models\TableRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a guest calls for a waiter or the bill, and again when staff
 * handle the call, so every floor screen clears it at once.
 */
class TableRequestRaised implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    public readonly int $requestId;

    public readonly int $tableId;

    public readonly TableRequestType $type;

    public readonly bool $handled;

    public function __construct(TableRequest $request)
    {
        $this->requestId = $request->id;
        $this->tableId = $request->dining_table_id;
        $this->type = $request->type;
        $this->handled = $request->handled_at !== null;
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
        return 'table.request';
    }

    /**
     * @return array{id: int, table_id: int, type: string, handled: bool}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->requestId,
            'table_id' => $this->tableId,
            'type' => $this->type->value,
            'handled' => $this->handled,
        ];
    }
}
