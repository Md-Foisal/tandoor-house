<?php

namespace App\Broadcasting;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * The single source of real-time channel names. Events, routes/channels.php
 * and the front end (resources/js/lib/realtime.ts) all use these strings.
 */
final class Channels
{
    public const STAFF_ORDERS = 'staff.orders';

    public const STAFF_KITCHEN = 'staff.kitchen';

    public const STAFF_FLOOR = 'staff.floor';

    public const STAFF_RESERVATIONS = 'staff.reservations';

    public const STAFF_DELIVERIES = 'staff.deliveries.{riderId}';

    public const ORDER = 'orders.{number}';

    public static function staffOrders(): PrivateChannel
    {
        return new PrivateChannel(self::STAFF_ORDERS);
    }

    public static function staffKitchen(): PrivateChannel
    {
        return new PrivateChannel(self::STAFF_KITCHEN);
    }

    public static function staffFloor(): PrivateChannel
    {
        return new PrivateChannel(self::STAFF_FLOOR);
    }

    public static function staffReservations(): PrivateChannel
    {
        return new PrivateChannel(self::STAFF_RESERVATIONS);
    }

    public static function rider(int $riderId): PrivateChannel
    {
        return new PrivateChannel(self::riderName($riderId));
    }

    public static function order(Order|string $order): PrivateChannel
    {
        return new PrivateChannel(self::orderName($order));
    }

    public static function riderName(int $riderId): string
    {
        return "staff.deliveries.{$riderId}";
    }

    public static function orderName(Order|string $order): string
    {
        return 'orders.'.($order instanceof Order ? $order->number : $order);
    }
}
