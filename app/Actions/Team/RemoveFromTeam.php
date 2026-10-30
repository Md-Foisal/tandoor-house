<?php

namespace App\Actions\Team;

use App\Enums\Role;
use App\Models\Shift;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveFromTeam
{
    public function __construct(private readonly ActivityRecorder $activity) {}

    public function __invoke(User $actor, User $target): void
    {
        abort_unless(TeamRules::canRemove($actor, $target), 403);

        if ($actor->is($target)) {
            throw ValidationException::withMessages(['role' => __('staff_team.errors.own_role')]);
        }

        $before = $target->role;

        DB::transaction(function () use ($target) {
            TeamRules::ensureOwnerRemains($target, Role::Customer);

            $target->forceFill(['role' => Role::Customer])->save();
            Shift::query()->where('user_id', $target->id)->where('starts_at', '>', now())->delete();
        });

        $this->activity->record('team.removed', __('staff_team.log.removed', [
            'user' => $actor->name,
            'name' => $target->name,
        ]), $target, ['from' => $before->value], $actor);
    }
}
