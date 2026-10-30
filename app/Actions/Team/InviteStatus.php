<?php

namespace App\Actions\Team;

use App\Models\StaffInvite;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Where a staff invite stands, read from the staff_invites table. */
class InviteStatus
{
    public const EVENT = 'automation.team.invite_sent';

    public const VALID_DAYS = 7;

    public static function expiresAfter(CarbonInterface $sent): CarbonInterface
    {
        return $sent->copy()->addDays(self::VALID_DAYS);
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{state: string, sent_at: string|null}>
     */
    public function forUsers(Collection $users): array
    {
        $invites = StaffInvite::query()
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $out = [];

        foreach ($users as $user) {
            $out[$user->id] = $this->state($invites->get($user->id));
        }

        return $out;
    }

    /**
     * @return array{state: string, sent_at: string|null}
     */
    public function forUser(User $user): array
    {
        return $this->forUsers(collect([$user]))[$user->id];
    }

    /**
     * @return array{state: string, sent_at: string|null}
     */
    private function state(?StaffInvite $invite): array
    {
        if ($invite === null) {
            return ['state' => 'none', 'sent_at' => null];
        }

        $state = match (true) {
            $invite->isAccepted() => 'accepted',
            $invite->isExpired() => 'expired',
            default => 'pending',
        };

        return ['state' => $state, 'sent_at' => $invite->sent_at->toIso8601String()];
    }
}
