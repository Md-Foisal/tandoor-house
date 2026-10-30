<?php

namespace App\Actions\Team;

use App\Models\Attendance;
use App\Models\User;
use App\Support\ActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixClockOut
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    /** A forgotten clock-out can be set up to this long after clocking in. */
    private const MAX_HOURS = 24;

    public function __invoke(User $actor, Attendance $attendance, string $clockedOutAt, string $reason): Attendance
    {
        abort_unless(TeamRules::canRoster($actor, $attendance->user) && ! $actor->is($attendance->user), 403);

        $now = CarbonImmutable::now(config('app.timezone'));

        $out = DB::transaction(function () use ($attendance, $clockedOutAt, $now) {
            $locked = Attendance::query()->whereKey($attendance->id)->lockForUpdate()->firstOrFail();
            $in = $locked->clocked_in_at;

            if ($locked->clocked_out_at !== null) {
                throw ValidationException::withMessages(['clocked_out_at' => __('staff_team.errors.already_clocked_out')]);
            }

            if ($in->addHours(WeekReport::MISSED_AFTER_HOURS)->greaterThanOrEqualTo($now)) {
                throw ValidationException::withMessages(['clocked_out_at' => __('staff_team.errors.not_missed')]);
            }

            $out = CarbonImmutable::parse($clockedOutAt, config('app.timezone'));

            if ($out->lessThanOrEqualTo($in) || $out->greaterThan($now) || $out->greaterThan($in->addHours(self::MAX_HOURS))) {
                throw ValidationException::withMessages(['clocked_out_at' => __('staff_team.errors.bad_clock_out', ['hours' => self::MAX_HOURS])]);
            }

            $locked->forceFill(['clocked_out_at' => $out])->save();

            return $out;
        });

        $attendance->refresh();

        $this->activity->record('attendance.fixed', __('staff_team.log.clock_out_fixed', [
            'user' => $actor->name,
            'name' => $attendance->user->name,
            'time' => $out->format('D j M H:i'),
            'reason' => $reason,
        ]), $attendance, ['reason' => $reason, 'clocked_out_at' => $out->toIso8601String()], $actor);

        return $attendance;
    }
}
