<?php

namespace App\Actions;

use App\Models\Order;
use App\Support\CodeGenerator;

class GenerateOrderNumber
{
    public function handle(): string
    {
        return CodeGenerator::unique(Order::class, 'number', 'TH-', 5);
    }
}
