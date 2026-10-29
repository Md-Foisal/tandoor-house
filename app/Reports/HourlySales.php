<?php

namespace App\Reports;

/**
 * Sales for one channel in one local hour.
 */
final class HourlySales
{
    public function __construct(
        public readonly string $date,
        public readonly int $hour,
        public readonly string $channel,
        public readonly int $orders,
        public readonly int $sales,
        public readonly int $service,
        public readonly int $tips,
    ) {}
}
