<?php

namespace App\Enums;

enum TableRequestType: string
{
    case CallWaiter = 'call_waiter';
    case Bill = 'bill';
}
