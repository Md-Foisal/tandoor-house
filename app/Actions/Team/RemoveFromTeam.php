<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RemoveFromTeam
{
    public function __invoke(User $actor, User $target): void
    {
        abort_unless(TeamRules::canRemove($actor, $target), 403);

        if ($actor->is($target)) {
            throw ValidationException::withMessages(['role' => __('staff_team.errors.own_role')]);
        }

        TeamRules::ensureOwnerRemains($target, Role::Customer);

        $before = $target->role;
        $target->forceFill(['role' => Role::Customer])->save();
        Shift::query()->where('user_id', $target->id)->where('starts_at', '>', now())->delete();

        TeamActivity::record('team.removed', $target, $actor, __('staff_team.log.removed', [
            'user' => $actor->name,
            'name' => $target->name,
        ]), ['from' => $before->value]);
    }
}
