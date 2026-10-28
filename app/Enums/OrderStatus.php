<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case Accepted = 'accepted';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case OutForDelivery = 'out_for_delivery';
    case Served = 'served';
    case Collected = 'collected';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
