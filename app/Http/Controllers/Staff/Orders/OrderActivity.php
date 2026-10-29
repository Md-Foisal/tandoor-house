<?php

namespace App\Http\Controllers\Staff\Orders;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\User;

class OrderActivity
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $event, Order $order, User $by, string $description, array $properties = []): void
    {
        ActivityLog::create([
            'event' => $event,
            'subject_type' => $order->getMorphClass(),
            'subject_id' => $order->id,
            'user_id' => $by->id,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}
