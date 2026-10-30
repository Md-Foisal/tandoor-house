<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Str;

class CreateStaffMember
{
    public function __construct(private readonly ActivityRecorder $activity, private readonly SendStaffInvite $invite) {}

    /**
     * The password is random and never shown to anyone: the invite email
     * lets the new person set their own.
     *
     * @param  array<string, mixed>  $data
     */
    public function __invoke(User $actor, array $data): User
    {
        $role = Role::from((string) $data['role']);
        TeamRules::ensureCanAssign($actor, $role);

        $user = new User([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => isset($data['phone']) ? (string) $data['phone'] : null,
            'password' => Str::random(40),
        ]);
        $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();

        $this->activity->record('team.added', __('staff_team.log.added', [
            'user' => $actor->name,
            'name' => $user->name,
            'role' => $role->label(),
        ]), $user, ['role' => $role->value], $actor);

        ($this->invite)($actor, $user);

        return $user;
    }
}
