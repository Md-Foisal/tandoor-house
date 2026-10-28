<?php

namespace Database\Seeders\Support;

use App\Services\OpeningHours;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Opening hours, demand and timestamp formatting for the demo data.
 */
final class Trading
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }

    /**
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public static function periods(CarbonInterface $day): array
    {
        return app(OpeningHours::class)->periodsOn($day);
    }

    public static function stamp(CarbonInterface $time): string
    {
        return CarbonImmutable::instance($time)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }

    /**
     * Relative demand through the day: a lunch bump and a bigger dinner peak.
     */
    public static function demand(CarbonInterface $time): float
    {
        $hour = $time->hour + $time->minute / 60;

        return 0.12
            + exp(-(($hour - 13.25) ** 2) / (2 * 0.6 ** 2))
            + 1.9 * exp(-(($hour - 19.75) ** 2) / (2 * 1.1 ** 2));
    }

    /**
     * A moment inside the day's service periods, weighted by demand, leaving
     * the last minutes before closing free of new orders.
     */
    public static function pickTime(CarbonImmutable $day, int $lastOrderMinutes = 30): ?CarbonImmutable
    {
        $buckets = [];

        foreach (self::periods($day) as [$open, $close]) {
            for ($t = $open; $t < $close->subMinutes($lastOrderMinutes); $t = $t->addMinutes(10)) {
                $buckets[] = [$t, self::demand($t)];
            }
        }

        if ($buckets === []) {
            return null;
        }

        return Dice::weighted($buckets)->addSeconds(mt_rand(0, 599));
    }
}
