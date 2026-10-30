<?php

namespace App\Actions\Team;

use App\Models\User;
use App\Notifications\Staff\StaffInvite;
use App\Support\Automation;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SendStaffInvite
{
    private const MAX_PER_HOUR = 3;

    public function __construct(private readonly Automation $automation, private readonly InviteStatus $status) {}

    /** Sends the first invite for a new person, with no limit or status check. */
    public function __invoke(User $actor, User $user): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(new StaffInvite($token, $actor->name));

        $this->automation->log(InviteStatus::EVENT, __('staff_team.log.invite_sent', [
            'user' => $actor->name,
            'name' => $user->name,
        ]), $user, ['invited_by' => $actor->id, 'valid_days' => InviteStatus::VALID_DAYS]);
    }

    /** Sends a fresh link, which replaces the old one, to someone who has not set a password yet. */
    public function resend(User $actor, User $user): void
    {
        TeamRules::ensureCanAssign($actor, $user->role);

        if (! in_array($this->status->forUser($user)['state'], ['pending', 'expired'], true)) {
            throw ValidationException::withMessages(['invite' => __('staff_team.errors.invite_not_needed')]);
        }

        $key = 'staff-invite:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages(['invite' => __('staff_team.errors.invite_throttled', [
                'minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60)),
            ])]);
        }

        RateLimiter::hit($key, 3600);

        ($this)($actor, $user);
    }
}
