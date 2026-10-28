<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderLine;

/**
 * The order summary shown on the home page, the account and the order
 * history. Callers must eager-load `lines`.
 */
class RecentOrderResource
{
    /**
     * @return array{number: string, placed_at: string, total: int, lines: array<int, array{name: string, quantity: int}>}
     */
    public static function make(Order $order): array
    {
        return [
            'number' => $order->number,
            'placed_at' => $order->placed_at->toIso8601String(),
            'total' => $order->total,
            'lines' => $order->lines
                ->map(fn (OrderLine $line) => ['name' => $line->name, 'quantity' => $line->quantity])
                ->values()
                ->all(),
        ];
    }
}
