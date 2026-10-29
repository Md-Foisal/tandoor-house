<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateStaffMember
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, User $target, array $data): User
    {
        abort_unless(TeamRules::canEdit($actor, $target) && $target->role->isStaff(), 403);

        $newRole = isset($data['role']) ? Role::from((string) $data['role']) : $target->role;
        $changesRole = $newRole !== $target->role;

        if ($changesRole) {
            if ($actor->is($target)) {
                throw ValidationException::withMessages(['role' => __('staff_team.errors.own_role')]);
            }

            TeamRules::ensureCanAssign($actor, $newRole);
            TeamRules::ensureOwnerRemains($target, $newRole);
        }

        $before = $target->role;
        $target->phone = isset($data['phone']) ? (string) $data['phone'] : null;
        $target->forceFill(['role' => $newRole])->save();

        if ($changesRole) {
            TeamActivity::record('team.role_changed', $target, $actor, __('staff_team.log.role_changed', [
                'user' => $actor->name,
                'name' => $target->name,
                'from' => $before->label(),
                'to' => $newRole->label(),
            ]), ['from' => $before->value, 'to' => $newRole->value]);
        }

        return $target;
    }
}
