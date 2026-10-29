<?php

namespace App\Actions\Team;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Rostered against clocked time for one week, with late arrivals and
 * clock-outs that were forgotten. Payroll and the tips split both read this.
 */
class WeekReport
{
    /** Minutes after the shift start before a clock-in counts as late. */
    public const LATE_AFTER_MINUTES = 5;

    /** Hours an open clock-in may run before it is treated as forgotten. */
    public const MISSED_AFTER_HOURS = 14;

    /** How early someone may clock in and still be matched to a shift. */
    private const MATCH_EARLY_HOURS = 3;

    /**
     * @return list<array<string, mixed>>
     */
    public function build(WeekRange $week): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        /** @var Collection<int, Shift> $shifts */
        $shifts = Shift::query()
            ->with('user:id,name,email,role')
            ->where('starts_at', '>=', $week->start)
            ->where('starts_at', '<', $week->end())
            ->orderBy('starts_at')
            ->get();

        /** @var EloquentCollection<int, Attendance> $attendances */
        $attendances = Attendance::query()
            ->with('user:id,name,email,role')
            ->where('clocked_in_at', '>=', $week->start)
            ->where('clocked_in_at', '<', $week->end())
            ->orderBy('clocked_in_at')
            ->get();

        $fixed = ActivityLog::query()
            ->where('event', 'attendance.fixed')
            ->where('subject_type', (new Attendance)->getMorphClass())
            ->whereIn('subject_id', $attendances->modelKeys())
            ->pluck('subject_id')
            ->all();

        $people = [];

        foreach ($shifts as $shift) {
            $id = $this->ensure($people, $shift->user);
            $people[$id]['rostered_minutes'] += self::minutes($shift->starts_at, $shift->ends_at);
        }

        $matched = [];

        foreach ($attendances as $attendance) {
            $id = $this->ensure($people, $attendance->user);
            $shift = $this->shiftFor($attendance, $shifts);
            $firstForShift = $shift !== null && ! isset($matched[$shift->id]);

            if ($shift !== null) {
                $matched[$shift->id] = true;
            }

            $in = $attendance->clocked_in_at->toImmutable();
            $open = $attendance->clocked_out_at === null;
            $missed = $open && $in->addHours(self::MISSED_AFTER_HOURS)->lessThan($now);
            $minutes = match (true) {
                $missed => 0,
                $open => self::minutes($in, $now),
                default => self::minutes($in, $attendance->clocked_out_at),
            };

            $lateSeconds = $firstForShift ? $in->getTimestamp() - $shift->starts_at->getTimestamp() : 0;
            $late = $lateSeconds > self::LATE_AFTER_MINUTES * 60 ? intdiv($lateSeconds, 60) : 0;

            $people[$id]['clocked_minutes'] += $minutes;
            $people[$id]['late'] += $late > 0 ? 1 : 0;
            $people[$id]['missed'] += $missed ? 1 : 0;
            $people[$id]['entries'][] = [
                'id' => $attendance->id,
                'in' => $in->toIso8601String(),
                'out' => $attendance->clocked_out_at?->toIso8601String(),
                'minutes' => $minutes,
                'open' => $open,
                'missed' => $missed,
                'fixed' => in_array($attendance->id, $fixed, true),
                'late_minutes' => $late,
                'shift' => $shift === null ? null : $shift->starts_at->format('H:i').'–'.$shift->ends_at->format('H:i'),
            ];
        }

        foreach ($shifts as $shift) {
            if ($shift->ends_at->lessThan($now) && ! isset($matched[$shift->id])) {
                $id = $this->ensure($people, $shift->user);
                $people[$id]['no_shows']++;
            }
        }

        $list = array_values($people);
        usort($list, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * @param  array<int, array<string, mixed>>  $people
     */
    private function ensure(array &$people, User $user): int
    {
        $people[$user->id] ??= [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'rostered_minutes' => 0,
            'clocked_minutes' => 0,
            'late' => 0,
            'missed' => 0,
            'no_shows' => 0,
            'entries' => [],
        ];

        return $user->id;
    }

    /**
     * @param  Collection<int, Shift>  $shifts
     */
    private function shiftFor(Attendance $attendance, Collection $shifts): ?Shift
    {
        $mine = $shifts->where('user_id', $attendance->user_id);

        if ($attendance->shift_id !== null && ($linked = $mine->firstWhere('id', $attendance->shift_id)) !== null) {
            return $linked;
        }

        $in = $attendance->clocked_in_at;

        return $mine->first(fn (Shift $shift) => $in->greaterThanOrEqualTo($shift->starts_at->subHours(self::MATCH_EARLY_HOURS)) && $in->lessThan($shift->ends_at));
    }

    public static function minutes(\DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return max(0, intdiv($to->getTimestamp() - $from->getTimestamp(), 60));
    }
}
