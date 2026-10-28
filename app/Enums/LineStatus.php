<?php

namespace App\Enums;

enum LineStatus: string
{
    case Queued = 'queued';
    case Cooking = 'cooking';
    case Ready = 'ready';
}
