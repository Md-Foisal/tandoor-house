<?php

namespace App\Reports;

use App\Enums\OrderChannel;
use Carbon\CarbonImmutable;

/**
 * A reporting period in whole Europe/London days, plus the equal-length
 * period it is compared with. `end` is exclusive (midnight after the last day).
 */
final class ReportRange
{
    public const MAX_DAYS = 366;

    public const PERIODS = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'custom'];

    /** Beyond this many days the day charts group by week. */
    public const WEEKLY_AFTER = 45;

    public function __construct(
        public readonly string $period,
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly CarbonImmutable $previousStart,
        public readonly CarbonImmutable $previousEnd,
        public readonly ?OrderChannel $channel = null,
    ) {}

    public static function resolve(string $period, ?string $from = null, ?string $to = null, ?OrderChannel $channel = null, ?CarbonImmutable $now = null): self
    {
        $tz = (string) config('app.timezone');
        $today = ($now ?? CarbonImmutable::now($tz))->setTimezone($tz)->startOfDay();
        $tomorrow = $today->addDay();

        [$start, $end] = match ($period) {
            'today' => [$today, $tomorrow],
            'yesterday' => [$today->subDay(), $today],
            'this_week' => [$today->startOfWeek(), $tomorrow],
            'last_week' => [$today->startOfWeek()->subWeek(), $today->startOfWeek()],
            'this_month' => [$today->startOfMonth(), $tomorrow],
            'last_month' => [$today->startOfMonth()->subMonthNoOverflow(), $today->startOfMonth()],
            default => [
                CarbonImmutable::parse((string) $from, $tz)->startOfDay(),
                CarbonImmutable::parse((string) $to, $tz)->startOfDay()->addDay(),
            ],
        };

        $days = self::countDays($start, $end);

        // A running week or month is compared with the same days of the one before.
        $previousStart = match ($period) {
            'this_week' => $start->subWeek(),
            'this_month' => $start->subMonthNoOverflow(),
            default => $start->subDays($days),
        };
        $previousEnd = $previousStart->addDays($days);

        if ($period === 'this_month' && $previousEnd->greaterThan($start)) {
            $previousEnd = $start;
        }

        return new self($period, $start, $end, $previousStart, $previousEnd, $channel);
    }

    public static function countDays(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $from = CarbonImmutable::parse($start->toDateString(), 'UTC');
        $to = CarbonImmutable::parse($end->toDateString(), 'UTC');

        return max(1, (int) round($from->diffInDays($to)));
    }

    public function days(): int
    {
        return self::countDays($this->start, $this->end);
    }

    public function weekly(): bool
    {
        return $this->days() > self::WEEKLY_AFTER;
    }

    public function lastDay(): CarbonImmutable
    {
        return $this->end->subDay();
    }

    public function label(): string
    {
        return self::describe($this->start, $this->lastDay());
    }

    public function previousLabel(): string
    {
        return self::describe($this->previousStart, $this->previousEnd->subDay());
    }

    public function fileSlug(): string
    {
        return $this->start->format('Y-m-d').'_'.$this->lastDay()->format('Y-m-d');
    }

    private static function describe(CarbonImmutable $first, CarbonImmutable $last): string
    {
        if ($first->isSameDay($last)) {
            return $first->format('D j M Y');
        }

        return $first->format($first->year === $last->year ? 'D j M' : 'D j M Y').' to '.$last->format('D j M Y');
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'period' => $this->period,
            'from' => $this->period === 'custom' ? $this->start->format('Y-m-d') : null,
            'to' => $this->period === 'custom' ? $this->lastDay()->format('Y-m-d') : null,
            'channel' => $this->channel?->value,
        ]);
    }
}
