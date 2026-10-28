<?php

namespace App\Enums;

enum TableStatus: string
{
    case Free = 'free';
    case Reserved = 'reserved';
    case Seated = 'seated';
    case BillRequested = 'bill_requested';
    case NeedsCleaning = 'needs_cleaning';
}
