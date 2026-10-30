<?php

namespace App\Enums;

enum LoyaltyKind: string
{
    case Earned = 'earned';
    case Reversed = 'reversed';
    case Redeemed = 'redeemed';
    case Returned = 'returned';
    case Manual = 'manual';
}
