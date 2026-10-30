<?php

namespace App\Actions\Loyalty;

use App\Models\Order;

/**
 * The wording of each ledger row, for display only. A row's identity per
 * order is its LoyaltyKind, which keeps every step safe to run twice.
 */
class LoyaltyReason
{
    public static function earned(Order $order): string
    {
        return __('automations.customers.loyalty.earned', ['number' => $order->number]);
    }

    public static function reversed(Order $order): string
    {
        return __('automations.customers.loyalty.reversed', ['number' => $order->number]);
    }

    public static function redeemed(Order $order): string
    {
        return __('automations.customers.loyalty.redeemed', ['number' => $order->number]);
    }

    public static function returned(Order $order): string
    {
        return __('automations.customers.loyalty.returned', ['number' => $order->number]);
    }
}
