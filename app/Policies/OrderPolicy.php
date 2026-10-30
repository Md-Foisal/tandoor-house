<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\GuestOrders;

class OrderPolicy
{
    /**
     * Customers see their own orders; guests the ones placed in their
     * session; staff see every order.
     */
    public function view(?User $user, Order $order): bool
    {
        if ($user?->isStaff()) {
            return true;
        }

        if ($user !== null && $order->user_id === $user->id) {
            return true;
        }

        return app(GuestOrders::class)->owns($order);
    }

    public function reorder(User $user, Order $order): bool
    {
        return $order->user_id === $user->id;
    }

    /**
     * One review per completed order, by the customer who placed it.
     */
    public function review(User $user, Order $order): bool
    {
        return $order->user_id === $user->id
            && $order->status === OrderStatus::Completed
            && ! $order->review()->exists();
    }

    /**
     * Only the customer who placed it, or the guest in the browser it was
     * placed from. Staff cancel through the order board.
     */
    public function cancel(?User $user, Order $order): bool
    {
        if ($user !== null && $order->user_id === $user->id) {
            return true;
        }

        return app(GuestOrders::class)->owns($order);
    }
}
