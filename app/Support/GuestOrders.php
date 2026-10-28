<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Contracts\Session\Session;

/**
 * Orders a guest placed in this browser session. Guests can follow and
 * act on only these; signed-in customers go through OrderPolicy.
 */
class GuestOrders
{
    private const KEY = 'guest_orders';

    public function __construct(private readonly Session $session) {}

    public function remember(Order $order): void
    {
        $numbers = (array) $this->session->get(self::KEY, []);

        $this->session->put(self::KEY, array_values(array_unique([...$numbers, $order->number])));
    }

    public function owns(Order $order): bool
    {
        return in_array($order->number, (array) $this->session->get(self::KEY, []), true);
    }
}
