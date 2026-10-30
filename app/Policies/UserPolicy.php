<?php

namespace App\Policies;

use App\Actions\Team\TeamRules;
use App\Enums\StaffArea;
use App\Models\User;

/**
 * Team management on the staff panel. Who may touch whom lives in
 * TeamRules; this adds the section check so both layers agree.
 */
class UserPolicy
{
    public function create(User $actor): bool
    {
        return StaffArea::Team->allows($actor->role);
    }

    public function update(User $actor, User $target): bool
    {
        return StaffArea::Team->allows($actor->role)
            && $target->role->isStaff()
            && TeamRules::canEdit($actor, $target);
    }

    /** Owners only, never themselves. */
    public function delete(User $actor, User $target): bool
    {
        return StaffArea::Team->allows($actor->role) && TeamRules::canRemove($actor, $target);
    }

    public function roster(User $actor, User $person): bool
    {
        return StaffArea::Team->allows($actor->role) && TeamRules::canRoster($actor, $person);
    }
}
