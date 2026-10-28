<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case PendingDeposit = 'pending_deposit';
    case Confirmed = 'confirmed';
    case Seated = 'seated';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';
}
