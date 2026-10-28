<?php

namespace App\Services;

use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Answers "are we open?" from the weekly hours and bank holiday closures,
 * always in the restaurant's local time.
 */
class OpeningHours
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Service periods for a date as [open, close] pairs.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function periodsOn(CarbonInterface $date): array
    {
        $day = CarbonImmutable::instance($date)->setTimezone(config('app.timezone'))->startOfDay();

        if (in_array($day->toDateString(), (array) $this->settings->get('bank_holidays', []), true)) {
            return [];
        }

        /** @var array<int|string, list<array{0: string, 1: string}>> $week */
        $week = (array) $this->settings->get('opening_hours', []);
        $periods = $week[$day->dayOfWeekIso] ?? $week[(string) $day->dayOfWeekIso] ?? [];

        return array_map(
            fn (array $period) => [
                $day->setTimeFromTimeString($period[0]),
                $day->setTimeFromTimeString($period[1]),
            ],
            $periods,
        );
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        return $this->currentPeriod($at) !== null;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function currentPeriod(?CarbonInterface $at = null): ?array
    {
        $at = CarbonImmutable::instance($at ?? now())->setTimezone(config('app.timezone'));

        foreach ($this->periodsOn($at) as $period) {
            if ($at->betweenIncluded($period[0], $period[1]->subSecond())) {
                return $period;
            }
        }

        return null;
    }

    public function nextOpening(?CarbonInterface $at = null): ?CarbonImmutable
    {
        $at = CarbonImmutable::instance($at ?? now())->setTimezone(config('app.timezone'));

        for ($offset = 0; $offset < 14; $offset++) {
            foreach ($this->periodsOn($at->addDays($offset)) as [$opens]) {
                if ($opens->greaterThan($at)) {
                    return $opens;
                }
            }
        }

        return null;
    }

    /**
     * The shape the OpenStatus component expects.
     *
     * @return array{isOpen: bool, until: string|null, opensAt: string|null}
     */
    public function status(?CarbonInterface $at = null): array
    {
        $at = CarbonImmutable::instance($at ?? now())->setTimezone(config('app.timezone'));
        $period = $this->currentPeriod($at);

        if ($period !== null) {
            return ['isOpen' => true, 'until' => $period[1]->format('H:i'), 'opensAt' => null];
        }

        $next = $this->nextOpening($at);

        return [
            'isOpen' => false,
            'until' => null,
            'opensAt' => $next === null ? null : $this->describe($next, $at),
        ];
    }

    /**
     * A week of opening hours for display, today first.
     *
     * @return list<array{day: string, isToday: bool, periods: list<string>}>
     */
    public function week(?CarbonInterface $from = null): array
    {
        $from = CarbonImmutable::instance($from ?? now())->setTimezone(config('app.timezone'));

        return array_map(function (int $offset) use ($from) {
            $date = $from->addDays($offset);

            return [
                'day' => $date->translatedFormat('l'),
                'isToday' => $offset === 0,
                'periods' => array_map(
                    fn (array $period) => $period[0]->format('H:i').'–'.$period[1]->format('H:i'),
                    $this->periodsOn($date),
                ),
            ];
        }, range(0, 6));
    }

    private function describe(CarbonImmutable $when, CarbonImmutable $now): string
    {
        $time = $when->format('H:i');

        return match (true) {
            $when->isSameDay($now) => __('ui.at_time', ['time' => $time]),
            $when->isSameDay($now->addDay()) => __('ui.tomorrow_at', ['time' => $time]),
            default => __('ui.day_at', ['day' => $when->translatedFormat('l'), 'time' => $time]),
        };
    }
}
