<?php

namespace App\Actions\Team;

use App\Enums\ReservationStatus;
use App\Enums\Role;
use App\Models\Reservation;
use App\Models\Shift;
use App\Models\User;
use App\Services\OpeningHours;
use Carbon\CarbonImmutable;

/** The week grid: people down the side, days across, shifts as blocks. */
class RotaBoard
{
    public function __construct(
        private readonly OpeningHours $hours,
        private readonly ShiftWarnings $warnings,
    ) {}

    /**
     * @return array{days: list<array<string, mixed>>, people: array<int, array<string, mixed>>}
     */
    public function build(WeekRange $week, ?User $actor = null): array
    {
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $week->start->addDays($i);
            $days[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('D'),
                'day' => $date->format('j M'),
                'is_today' => $date->isToday(),
                'periods' => array_map(fn (array $p) => $p[0]->format('H:i').'–'.$p[1]->format('H:i'), $this->hours->periodsOn($date)),
                'covers' => 0,
            ];
        }

        $covers = Reservation::query()
            ->whereIn('status', [ReservationStatus::Confirmed, ReservationStatus::Seated, ReservationStatus::Completed])
            ->where('starts_at', '>=', $week->start)
            ->where('starts_at', '<', $week->end())
            ->get(['starts_at', 'party_size']);

        foreach ($days as $i => $day) {
            $days[$i]['covers'] = (int) $covers->filter(fn (Reservation $r) => $r->starts_at->toDateString() === $day['date'])->sum('party_size');
        }

        $shifts = Shift::query()
            ->where('starts_at', '>=', $week->start)
            ->where('starts_at', '<', $week->end())
            ->orderBy('starts_at')
            ->get();

        $people = User::query()
            ->where('role', '!=', Role::Customer)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $rows = $people->map(function (User $person) use ($shifts, $week, $actor) {
            $mine = $shifts->where('user_id', $person->id);

            return [
                'id' => $person->id,
                'name' => $person->name,
                'role' => $person->role->value,
                'can_roster' => $actor === null || TeamRules::canRoster($actor, $person),
                'minutes' => (int) $mine->sum(fn (Shift $s) => WeekReport::minutes($s->starts_at, $s->ends_at)),
                'days' => collect(range(0, 6))->map(fn (int $i) => $mine
                    ->filter(fn (Shift $s) => $s->starts_at->toDateString() === $week->start->addDays($i)->toDateString())
                    ->map(fn (Shift $s) => $this->block($s))
                    ->values()
                    ->all())->all(),
            ];
        })->values()->all();

        return ['days' => $days, 'people' => $rows];
    }

    /**
     * @return array<string, mixed>
     */
    private function block(Shift $shift): array
    {
        return [
            'id' => $shift->id,
            'user_id' => $shift->user_id,
            'date' => $shift->starts_at->toDateString(),
            'start' => $shift->starts_at->format('H:i'),
            'end' => $shift->ends_at->format('H:i'),
            'role' => $shift->role->value,
            'notes' => $shift->notes,
            'outside_hours' => $this->warnings->outsideHours(CarbonImmutable::instance($shift->starts_at), CarbonImmutable::instance($shift->ends_at)),
        ];
    }
}
