<?php

namespace App\Actions\Team;

use Carbon\CarbonImmutable;

class WeekRange
{
    public function __construct(
        public readonly CarbonImmutable $start,
    ) {}

    /** Any date inside the week selects it; no or bad input means this week. */
    public static function from(?string $date): self
    {
        $zone = config('app.timezone');

        try {
            $day = $date === null || $date === '' ? CarbonImmutable::now($zone) : CarbonImmutable::parse($date, $zone);
        } catch (\Throwable) {
            $day = CarbonImmutable::now($zone);
        }

        return new self($day->startOfWeek());
    }

    public function end(): CarbonImmutable
    {
        return $this->start->addDays(7);
    }

    public function previous(): CarbonImmutable
    {
        return $this->start->subDays(7);
    }

    public function next(): CarbonImmutable
    {
        return $this->start->addDays(7);
    }

    public function isCurrent(): bool
    {
        return $this->start->equalTo(CarbonImmutable::now(config('app.timezone'))->startOfWeek());
    }

    public function label(): string
    {
        $last = $this->start->addDays(6);

        return $this->start->format('j M').' – '.$last->format('j M Y');
    }
}
