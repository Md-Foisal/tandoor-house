<?php

namespace App\Policies;

use App\Actions\Team\TeamRules;
use App\Enums\StaffArea;
use App\Models\Shift;
use App\Models\User;

/**
 * Who is rostered is checked again by SaveShift and CopyWeek, because the
 * person comes from the request body rather than the route.
 */
class ShiftPolicy
{
    public function create(User $user): bool
    {
        return StaffArea::Team->allows($user->role);
    }

    public function update(User $user, Shift $shift): bool
    {
        return StaffArea::Team->allows($user->role) && TeamRules::canRoster($user, $shift->user);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return StaffArea::Team->allows($user->role) && TeamRules::canRoster($user, $shift->user);
    }

    public function copyWeek(User $user): bool
    {
        return StaffArea::Team->allows($user->role);
    }
}
