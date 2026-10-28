<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case ApplePay = 'apple_pay';
    case GooglePay = 'google_pay';
    case PayAtVenue = 'pay_at_venue';
    case Cash = 'cash';
}
