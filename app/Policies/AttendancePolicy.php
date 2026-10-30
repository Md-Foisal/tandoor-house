<?php

namespace App\Policies;

use App\Actions\Team\TeamRules;
use App\Enums\StaffArea;
use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    /** Every member of staff clocks themselves in and out. */
    public function clock(User $user): bool
    {
        return StaffArea::Today->allows($user->role);
    }

    /** A manager fixes someone else's forgotten clock-out, never their own. */
    public function fix(User $user, Attendance $attendance): bool
    {
        return StaffArea::Team->allows($user->role)
            && ! $user->is($attendance->user)
            && TeamRules::canRoster($user, $attendance->user);
    }

    public function export(User $user): bool
    {
        return StaffArea::Team->allows($user->role);
    }
}
