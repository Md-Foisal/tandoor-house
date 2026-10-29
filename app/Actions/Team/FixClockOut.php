<?php

namespace App\Actions\Team;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class FixClockOut
{
    /** A forgotten clock-out can be set up to this long after clocking in. */
    private const MAX_HOURS = 24;

    public function __invoke(User $actor, Attendance $attendance, string $clockedOutAt, string $reason): Attendance
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $in = $attendance->clocked_in_at;

        if ($attendance->clocked_out_at !== null || $in->addHours(WeekReport::MISSED_AFTER_HOURS)->greaterThanOrEqualTo($now)) {
            throw ValidationException::withMessages(['clocked_out_at' => __('staff_team.errors.not_missed')]);
        }

        $out = CarbonImmutable::parse($clockedOutAt, config('app.timezone'));

        if ($out->lessThanOrEqualTo($in) || $out->greaterThan($now) || $out->greaterThan($in->addHours(self::MAX_HOURS))) {
            throw ValidationException::withMessages(['clocked_out_at' => __('staff_team.errors.bad_clock_out', ['hours' => self::MAX_HOURS])]);
        }

        $attendance->forceFill(['clocked_out_at' => $out])->save();

        TeamActivity::record('attendance.fixed', $attendance, $actor, __('staff_team.log.clock_out_fixed', [
            'user' => $actor->name,
            'name' => $attendance->user->name,
            'time' => $out->format('D j M H:i'),
            'reason' => $reason,
        ]), ['reason' => $reason, 'clocked_out_at' => $out->toIso8601String()]);

        return $attendance;
    }
}
