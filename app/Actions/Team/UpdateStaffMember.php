<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Validation\ValidationException;

class UpdateStaffMember
{
    public function __construct(private readonly ActivityRecorder $activity) {}

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
        if (array_key_exists('phone', $data)) {
            $target->phone = $data['phone'] === null || $data['phone'] === '' ? null : (string) $data['phone'];
        }

        $target->forceFill(['role' => $newRole])->save();

        if ($changesRole) {
            $this->activity->record('team.role_changed', __('staff_team.log.role_changed', [
                'user' => $actor->name,
                'name' => $target->name,
                'from' => $before->label(),
                'to' => $newRole->label(),
            ]), $target, ['from' => $before->value, 'to' => $newRole->value], $actor);
        }

        return $target;
    }
}
