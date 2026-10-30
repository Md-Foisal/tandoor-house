<?php

namespace App\Actions\Team;

use App\Services\OpeningHours;
use Carbon\CarbonInterface;

class ShiftWarnings
{
    public function __construct(private readonly OpeningHours $hours) {}

    /** True when the shift misses every service period of its day (touching counts as inside). */
    public function outsideHours(CarbonInterface $start, CarbonInterface $end): bool
    {
        foreach ($this->hours->periodsOn($start) as [$opens, $closes]) {
            if ($start->lessThanOrEqualTo($closes) && $end->greaterThanOrEqualTo($opens)) {
                return false;
            }
        }

        return true;
    }
}
