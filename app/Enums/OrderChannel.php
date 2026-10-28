<?php

namespace App\Enums;

enum OrderChannel: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
    case DineIn = 'dine_in';
}
