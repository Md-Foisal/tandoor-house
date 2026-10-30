<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\AutoAcceptOrder;
use App\Support\Automation;

class AutoAcceptOnOrderPlaced
{
    public function __construct(private readonly Automation $automation) {}

    public function handle(OrderPlaced $event): void
    {
        if ($this->automation->enabled(Automation::AUTO_ACCEPT)) {
            AutoAcceptOrder::dispatch($event->orderId);
        }
    }
}
