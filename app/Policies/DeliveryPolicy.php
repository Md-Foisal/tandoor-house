<?php

namespace App\Policies;

use App\Enums\OrderChannel;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

/**
 * Per-run rules for the rider view, on top of the "staff.deliveries" gate.
 * Not auto-discovered: the delivery controllers call it directly.
 */
class DeliveryPolicy
{
    public function manages(User $user): bool
    {
        return in_array($user->role, [Role::Manager, Role::Owner], true);
    }

    /** Riders act only on their own runs; managers and owners on any. */
    public function act(User $user, Order $order): bool
    {
        if ($order->channel !== OrderChannel::Delivery) {
            return false;
        }

        return $this->manages($user) || ($user->role === Role::Rider && $order->rider_id === $user->id);
    }

    public function assign(User $user, Order $order): bool
    {
        return $this->manages($user) && $order->channel === OrderChannel::Delivery;
    }

    /** The rider takes cash at the door when the order is not already paid. */
    public function collectsCash(Order $order): bool
    {
        return $order->payment_status !== PaymentStatus::Paid
            && in_array($order->payment_method, [PaymentMethod::Cash, PaymentMethod::PayAtVenue], true);
    }
}
